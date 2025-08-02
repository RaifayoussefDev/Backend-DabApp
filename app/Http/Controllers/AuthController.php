<?php

namespace App\Http\Controllers;

use App\Models\Authentication;
use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\EmailExists;

use Kreait\Firebase\Exception\Auth\InvalidPassword;
use Kreait\Firebase\Exception\Auth\UserNotFound;


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
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // 🔥 Créer l’utilisateur dans Firebase
        try {
            $auth = (new Factory)
                ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
                ->createAuth();

            $firebaseUser = $auth->createUser([
                'email' => $request->email,
                'password' => $request->password,
                'displayName' => $request->first_name . ' ' . $request->last_name,
            ]);
        } catch (EmailExists $e) {
            return response()->json(['error' => 'Cet email existe déjà sur Firebase'], 409);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erreur Firebase : ' . $e->getMessage()], 500);
        }

        // ✅ Créer l’utilisateur dans ta BDD
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'password'   => Hash::make($request->password),
            'role_id'    => $request->role_id,
            'verified'   => false, // ou true si email_verified de Firebase
            'is_active'  => true,
            'is_online'  => false,
            'language'   => 'fr',
            'timezone'   => 'Africa/Casablanca',
            'two_factor_enabled' => false,
            'country_id' => null,
        ]);

        $token = JWTAuth::fromUser($user);
        $tokenExpiration = now()->addMonth();

        return response()->json([
            'user' => $user,
            'token' => $token,
            'expires_at' => $tokenExpiration->toDateTimeString()
        ]);
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

        $user = User::where('email', $login)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Utilisateur inactif'], 403);
        }

        $user->is_online = 1;
        $user->last_login = now();
        $user->save();

        // ✅ Extract country & continent from proxy headers
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // 🔐 Add country and continent to JWT
        $token = JWTAuth::claims([
            'country' => $country,
            'continent' => $continent,
        ])->fromUser($user);

        $tokenExpiration = now()->addMonth();

        Authentication::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'token_expiration' => $tokenExpiration,
                'is_online' => true,
                'connection_date' => now(),
            ]
        );

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

            $user->notify(new SendOtpNotification($otp));

            return response()->json([
                'message' => 'OTP required',
                'user_id' => $user->id,
                'requiresOTP' => true,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'country' => $country
            ], 202);
        }

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_expiration' => $tokenExpiration,
            'country' => $country,
            'continent' => $continent
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
            'login' => 'required|string', // email ou téléphone
            'otp' => 'required|string',
        ]);

        // Rechercher l'utilisateur par login
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Utilisateur inactif'], 403);
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

        // ✅ Extraire les infos de localisation
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // 🔐 Générer le token JWT
        $token = JWTAuth::claims([
            'country' => $country,
            'continent' => $continent,
        ])->fromUser($user);

        $tokenExpiration = now()->addMonth();

        // 📝 Mettre à jour l'utilisateur
        $user->token_expiration = $tokenExpiration;
        $user->is_online = true;
        $user->last_login = now();
        $user->save();

        // 🔄 Mettre à jour ou créer le token dans la table authentications
        Authentication::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'token_expiration' => $tokenExpiration,
                'is_online' => true,
                'connection_date' => now(),
            ]
        );

        // ✅ Retour uniforme
        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'two_factor_enabled' => $user->two_factor_enabled,
                'is_active' => $user->is_active,
            ],
            'token' => $token,
            'token_expiration' => $tokenExpiration,
            'country' => $country,
            'continent' => $continent
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
            'wishlists.listing',
            'wishlists.listing.images',
            'listings.motorcycle',
            'listings.motorcycle.brand',
            'listings.motorcycle.model',
            'listings.motorcycle.year',
            'listings.motorcycle.type',
            'listings.images',
            'auctionHistoriesAsSeller.listing',
            'auctionHistoriesAsBuyer.listing',
            'auctionHistoriesAsSeller.listing',
            'auctionHistoriesAsBuyer.listing',
            'bankCards',
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
        $token = auth()->refresh();
        $tokenExpiration = now()->addMonth();

        return response()->json([
            'token' => $token,
            'expires_at' => $tokenExpiration->toDateTimeString()
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
    /**
     * @OA\Put(
     *     path="/api/user/two-factor-toggle",
     *     summary="Enable or disable two-factor authentication",
     *     description="Toggles two-factor auth for the authenticated user",
     *     operationId="toggleTwoFactor",
     *     tags={"Authentification"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication enabled."),
     *             @OA\Property(property="two_factor_enabled", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    public function toggleTwoFactor(Request $request)
    {
        $user = $request->user();

        $user->two_factor_enabled = !$user->two_factor_enabled;
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication ' . ($user->two_factor_enabled ? 'enabled' : 'disabled') . '.',
            'two_factor_enabled' => $user->two_factor_enabled,
        ]);
    }
}
