<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Authentication;
use App\Notifications\SendOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Kreait\Firebase\Factory;

class FirebasePhoneAuthController extends Controller
{
    /**
     * Authentification avec numéro de téléphone et mot de passe
     * → Envoie automatiquement un OTP Firebase par SMS
     */
    /**
     * swagger
     * @OA\Post(
     *     path="/auth/login-with-phone-password",
     *     tags={"Authentication-firebase"},
     *     summary="Login with phone and password",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="password", type="string", example="your_password_here")
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Credentials valid, proceed with SMS verification",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Credentials valid, proceed with SMS verification"),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="requiresFirebaseOTP", type="boolean", example=true),
     *             @OA\Property(property="email", type="string", example="test@test.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Nom d'utilisateur ou mot de passe incorrect")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="User inactive",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Utilisateur inactif")
     *         )
     *     )
     * )
     */
    public function loginWithPhonePassword(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string'
        ]);

        $phone = $request->input('phone');
        $password = $request->input('password');

        // 🎯 Recherche de l'utilisateur par téléphone
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json(['error' => 'Nom d\'utilisateur ou mot de passe incorrect'], 401);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Nom d\'utilisateur ou mot de passe incorrect'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Utilisateur inactif'], 403);
        }

        // 🟢 Marquer comme connecté
        $user->is_online = 1;
        $user->last_login = now();
        $user->save();

        // 🔐 Générer le token JWT Laravel
        $token = JWTAuth::fromUser($user);
        $tokenExpiration = now()->addMonth();

        // 📌 Traçage de la connexion
        Authentication::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'token_expiration' => $tokenExpiration,
                'is_online' => true,
                'connection_date' => now(),
            ]
        );

        // 📱 TOUJOURS envoyer un OTP par SMS via Firebase
        return response()->json([
            'message' => 'Credentials valid, proceed with SMS verification',
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'requiresFirebaseOTP' => true,
            'email' => $user->email
        ], 202);
    }

    /**
     * Vérification de l'OTP après authentification (méthode classique - gardée pour compatibilité)
     */
    /**
     * swagger
     * @OA\Post(
     *     path="/auth/verify-otp",
     *     tags={"Authentication-firebase"},
     *     summary="Verify OTP after authentication",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="otp", type="string", example="1234")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP valid, authentication successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object", ref="#/components/schemas/User"),
     *             @OA\Property(property="token", type="string", example="your_jwt_token_here"),
     *             @OA\Property(property="token_expiration", type="string", example="2023-10-01T00:00:00Z"),
     *            @OA\Property(property="country", type="string", example="FR"),
     *            @OA\Property(property="continent", type="string", example="Europe")
     *        )
     *    ),
     *
     *   @OA\Response(
     *       response=404,
     *      description="User not found",
     *      @OA\JsonContent(
     *          @OA\Property(property="error", type="string", example="Utilisateur non trouvé")
     *     )
     *  ),
     *  @OA\Response(
     *      response=401,
     *     description="Invalid session",
     *     @OA\JsonContent(
     *         @OA\Property(property="error", type="string", example="Session invalide")
     *    )
     * )
     * )
     * */
    public function verifyOTP(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'otp' => 'required|string|size:4'
        ]);

        $userId = $request->input('user_id');
        $otp = $request->input('otp');

        // Vérifier l'OTP
        $otpRecord = DB::table('otps')
            ->where('user_id', $userId)
            ->where('code', $otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['error' => 'OTP invalide ou expiré'], 401);
        }

        // Supprimer l'OTP utilisé
        DB::table('otps')->where('user_id', $userId)->delete();

        // Récupérer l'utilisateur
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Récupérer le token depuis la table Authentication
        $auth = Authentication::where('user_id', $userId)->first();

        if (!$auth) {
            return response()->json(['error' => 'Session invalide'], 401);
        }

        // ✅ Ajouter les données de localisation
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        return response()->json([
            'user' => $user,
            'token' => $auth->token,
            'token_expiration' => $auth->token_expiration,
            'country' => $country,
            'continent' => $continent
        ]);
    }


    /**
     * Finaliser l'authentification après vérification Firebase OTP
     */
    /**
     * swagger
     * @OA\Post(
     *    path="/auth/complete-firebase-auth",
     *   tags={"Authentication-firebase"},
     *   summary="Complete Firebase authentication after OTP verification",
     *    @OA\RequestBody(
     *        required=true,
     *       @OA\JsonContent(
     *            type="object",
     *           @OA\Property(property="user_id", type="integer", example=1),
     *          @OA\Property(property="idToken", type="string", example="your_firebase_id_token_here")
     *       )
     *   ),
     *   @OA\Response(
     *       response=200,
     *      description="Firebase token valid, authentication successful",
     *      @OA\JsonContent(
     *          @OA\Property(property="user", type="object", ref="#/components/schemas/User"),
     *         @OA\Property(property="token", type="string", example="your_jwt_token_here"),
     *       @OA\Property(property="token_expiration", type="string", example="2023-10-01T00:00:00Z"),
     *        @OA\Property(property="country", type="string", example="FR"),
     *       @OA\Property(property="continent", type="string", example="Europe")
     *       )
     *  ),
     *  @OA\Response(
     *      response=404,
     *     description="User not found",
     *    @OA\JsonContent(
     *        @OA\Property(property="error", type="string", example="Utilisateur non trouvé")
     *   )
     * ),
     * @OA\Response(
     *     response=401,
     *    description="Invalid session or phone number mismatch",
     *   @OA\JsonContent(
     *       @OA\Property(property="error", type="string", example="Session invalide ou numéro de téléphone non correspondant")
     *  )
     * )
     * )
     * */

    public function completeFirebaseAuth(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'idToken' => 'required|string',
        ]);

        $userId = $request->input('user_id');
        $idToken = $request->input('idToken');

        // Initialiser Firebase Auth
        $auth = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
            ->createAuth();

        try {
            $verifiedIdToken = $auth->verifyIdToken($idToken);
            $firebaseUser = $auth->getUser($verifiedIdToken->claims()->get('sub'));

            // Récupérer l'utilisateur
            $user = User::find($userId);

            if (!$user) {
                return response()->json(['error' => 'Utilisateur non trouvé'], 404);
            }

            // Vérifier que le numéro correspond
            if ($firebaseUser->phoneNumber !== $user->phone) {
                return response()->json(['error' => 'Numéro de téléphone non correspondant'], 401);
            }

            // Récupérer le token depuis la table Authentication
            $authRecord = Authentication::where('user_id', $userId)->first();

            if (!$authRecord) {
                return response()->json(['error' => 'Session invalide'], 401);
            }

            // ✅ Ajouter les données de localisation
            $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
            $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

            return response()->json([
                'user' => $user,
                'token' => $authRecord->token,
                'token_expiration' => $authRecord->token_expiration,
                'country' => $country,
                'continent' => $continent
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Token Firebase invalide : ' . $e->getMessage()
            ], 401);
        }
    }
}
