<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;



class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Enregistrer un nouvel utilisateur",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name","last_name","email","phone","password","password_confirmation"},
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="phone", type="string", example="+123456789"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="secret123"),
     *             @OA\Property(property="role_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inscription réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+123456789"),
     *                 @OA\Property(property="role_id", type="integer", example=2)
     *             ),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLC...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Données invalides"
     *     )
     * )
     */

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:6|confirmed', // password_confirmation obligatoire
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'password'   => Hash::make($request->password),
            'role_id' => $request->role_id
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json(compact('user', 'token'));
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Connexion d'un utilisateur",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login","password"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie (sans 2FA)",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+123456789"),
     *                 @OA\Property(property="role_id", type="integer", example=2)
     *             ),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLC..."),
     *             @OA\Property(property="token_expiration", type="string", example="2025-04-17 10:45:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Connexion avec OTP requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP requis"),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="phone", type="string", example="+123456789")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Identifiants invalides",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur inactif",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="User is inactive")
     *         )
     *     )
     * )
     */


    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string'
        ]);

        $login = $request->input('login');
        $password = $request->input('password');

        // Check if login is email or phone
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $login)->first()
            : User::where('phone', $login)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Verify if user is active
        if (!$user->is_active) {
            return response()->json(['error' => 'User is inactive'], 401);
        }

        // Update is_online in the users table (not in authentication table)
        $user->is_online = 1; // Set user as online
        $user->save();

        // Generate JWT token
        $token = JWTAuth::fromUser($user);
        $tokenExpiration = now()->addHour();

        // If 2FA is enabled for the user
        if ($user->two_factor_enabled) {
            $otp = rand(1000, 9999);
            DB::table('otps')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'code' => $otp,
                    'expires_at' => now()->addMinutes(5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json([
                'message' => 'OTP required',
                'user_id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'requiresOTP' => $user->two_factor_enabled,
                'email' => $user->email
            ], 202); // Accepted
        }

        // If no 2FA, return the JWT token and its expiration
        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_expiration' => $tokenExpiration
        ]);
    }



    /**
     * @OA\Post(
     *     path="/api/verify-otp",
     *     summary="Vérification du code OTP pour l'authentification à deux facteurs",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login", "otp"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="otp", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP valide, authentification réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+123456789"),
     *                 @OA\Property(property="role_id", type="integer", example=2)
     *             ),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLC..."),
     *             @OA\Property(property="token_expiration", type="string", example="2025-04-17 10:45:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="OTP invalide ou expiré",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid or expired OTP")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur introuvable",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="User not found")
     *         )
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'login' => 'required|string', // email ou phone
            'otp' => 'required|string',
        ]);

        // Rechercher l'utilisateur par login
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Vérifier l'OTP
        $otpRecord = DB::table('otps')
            ->where('user_id', $user->id)
            ->where('code', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['error' => 'Invalid or expired OTP'], 401);
        }

        // Supprimer l'OTP après usage
        DB::table('otps')->where('id', $otpRecord->id)->delete();

        // Authentifier l'utilisateur
        $token = JWTAuth::fromUser($user);
        $tokenExpiration = now()->addHour();

        $user->token_expiration = $tokenExpiration;
        $user->save();

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_expiration' => $tokenExpiration,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/me",
     *     summary="Récupérer les informations de l'utilisateur authentifié",
     *     tags={"Authentification"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations de l'utilisateur récupérées avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+123456789"),
     *                 @OA\Property(property="role_id", type="integer", example=2),
     *
     *                 @OA\Property(property="bank_cards", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="card_number", type="string", example="1234567890123456"),
     *                         @OA\Property(property="expiry_date", type="string", example="12/24"),
     *                         @OA\Property(property="cvv", type="string", example="123")
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="wishlists", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="listing_id", type="integer", example=12),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-04-24T11:33:12"),
     *                         @OA\Property(property="listing", type="object",
     *                             @OA\Property(property="id", type="integer", example=12),
     *                             @OA\Property(property="title", type="string", example="Yamaha R6 2020"),
     *                             @OA\Property(property="price", type="number", format="float", example=68000)
     *                         )
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="listings", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=18),
     *                         @OA\Property(property="title", type="string", example="Honda CBR 600RR"),
     *                         @OA\Property(property="price", type="number", format="float", example=72000),
     *                         @OA\Property(property="status", type="string", example="active")
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="auction_histories_as_seller", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="bid_amount", type="number", format="float", example=60000),
     *                         @OA\Property(property="bid_date", type="string", format="date-time", example="2025-04-23T10:45:00"),
     *                         @OA\Property(property="listing", type="object",
     *                             @OA\Property(property="id", type="integer", example=18),
     *                             @OA\Property(property="title", type="string", example="Honda CBR 600RR")
     *                         )
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="auction_histories_as_buyer", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=9),
     *                         @OA\Property(property="bid_amount", type="number", format="float", example=65000),
     *                         @OA\Property(property="bid_date", type="string", format="date-time", example="2025-04-23T14:15:00"),
     *                         @OA\Property(property="listing", type="object",
     *                             @OA\Property(property="id", type="integer", example=21),
     *                             @OA\Property(property="title", type="string", example="Kawasaki Ninja 400")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Utilisateur non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */

    public function me(Request $request)
    {
        $user = $request->user();

        $userWithData = User::with([
            'bankCards',
            'wishlists.listing',

            'listings.motorcycle',
            'listings.motorcycle.brand',
            'listings.motorcycle.model',
            'listings.motorcycle.year',
            'listings.motorcycle.type',
            'auctionHistoriesAsSeller.listing',
            'auctionHistoriesAsBuyer.listing',
            'auctionHistoriesAsSeller.listing',
            'auctionHistoriesAsBuyer.listing',
        ])->find($user->id);

        // Déchiffrer les CVV
        foreach ($userWithData->bankCards as $card) {
            if (!empty($card->cvv)) {
                try {
                    $card->cvv = decrypt($card->cvv);
                } catch (\Exception $e) {
                    $card->cvv = null;
                }
            }
        }

        return response()->json([
            'user' => $userWithData
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Déconnexion de l'utilisateur",
     *     tags={"Authentification"},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Déconnexion réussie")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur"
     *     )
     * )
     */
    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }
    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     summary="Rafraîchir le token JWT",
     *     tags={"Authentification"},
     *     @OA\Response(
     *         response=200,
     *         description="Token rafraîchi avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLC...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token invalide ou expiré",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Token expired or invalid")
     *         )
     *     )
     * )
     */
    public function refresh()
    {
        return response()->json([
            'token' => auth()->refresh()
        ]);
    }
    /**
     * @OA\Put(
     *     path="/api/user/update",
     *     summary="Mettre à jour le profil de l'utilisateur",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="phone", type="string", example="+123456789"),
     *             @OA\Property(property="birthday", type="string", format="date", example="1990-05-15")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profil mis à jour",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Données invalides"
     *     )
     * )
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'email'      => 'nullable|email|unique:users,email,' . $user->id,
            'phone'      => 'nullable|string|unique:users,phone,' . $user->id,
            'birthday'   => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user->update($request->only('first_name', 'last_name', 'email', 'phone', 'birthday'));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }
}
