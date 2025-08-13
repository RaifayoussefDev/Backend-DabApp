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

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;



class AuthController extends Controller
{
    private $whatsappApiUrl = 'https://api.360messenger.com/v2/sendMessage';
    private $whatsappApiToken = 'pkxzQcGle8PEQmloc70XC8wYMLeNMemwGtd';
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
            'two_factor_enabled' => true,
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

            // 🚀 Essayer WhatsApp en premier, puis email en fallback
            $otpSentVia = $this->sendOtpWithFallback($user, $otp);

            // Vérifier si l'envoi a échoué
            if ($otpSentVia === 'failed') {
                return response()->json([
                    'error' => 'Impossible d\'envoyer le code OTP. Veuillez réessayer plus tard.',
                    'user_id' => $user->id
                ], 500);
            }

            return response()->json([
                'message' => 'OTP required',
                'user_id' => $user->id,
                'requiresOTP' => true,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'country' => $country,
                'otp_sent_via' => $otpSentVia // 'whatsapp', 'email', ou 'failed'
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
     * Send OTP via WhatsApp first, fallback to email if WhatsApp fails
     */
    private function sendOtpWithFallback(User $user, $otp)
    {
        // First, try WhatsApp if user has phone number
        if (!empty($user->phone)) {
            $whatsappSent = $this->sendWhatsAppOtp($user->phone, $otp);

            if ($whatsappSent) {
                Log::info('OTP sent via WhatsApp', [
                    'user_id' => $user->id,
                    'phone' => $user->phone
                ]);
                return 'whatsapp';
            }
        }

        // Fallback to email
        try {
            $user->notify(new SendOtpNotification($otp));
            Log::info('OTP sent via Email (WhatsApp fallback)', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => empty($user->phone) ? 'no_phone' : 'whatsapp_failed'
            ]);
            return 'email';
        } catch (\Exception $e) {
            Log::error('Failed to send OTP via both WhatsApp and Email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return 'failed';
        }
    }


    /**
     * Send OTP via WhatsApp
     */
    private function sendWhatsAppOtp($phone, $otp)
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phone);

            $payload = [
                'phonenumber' => '+' . $phoneNumber,
                'text' => "🔐 Your OTP code from dabapp.co is: {$otp}\n\nThis code expires in 5 minutes.\nNever share this code with anyone."
            ];

            Log::info('Attempting WhatsApp OTP send', [
                'phone' => $phoneNumber,
                'formatted_phone' => '+' . $phoneNumber
            ]);

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer {$this->whatsappApiToken}",
                'Content-Type' => 'application/json',
            ])->post($this->whatsappApiUrl, $payload);

            Log::info('WhatsApp API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp OTP send failed', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Format phone number for Morocco
     */
    private function formatPhoneNumber($phone)
    {
        // Nettoyer le numéro de tous les caractères non numériques sauf le +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Si le numéro commence par +, enlever le +
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        // Si le numéro ne commence pas par un code pays, on le retourne tel quel
        // (l'utilisateur devra fournir un numéro au format international)
        return $phone;
    }

    /**
     * @OA\Post(
     *     path="/api/resend-otp",
     *     summary="Resend OTP code for two-factor authentication",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="method", type="string", enum={"whatsapp", "email"}, example="email", description="Preferred method to send OTP")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP code has been resent"),
     *             @OA\Property(property="otp_sent_via", type="string", example="email"),
     *             @OA\Property(property="user_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Please wait before requesting another OTP")
     *         )
     *     )
     * )
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'method' => 'nullable|string|in:whatsapp,email'
        ]);

        // Find user by login (email or phone)
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // ✅ Generate a new OTP code
        $otp = rand(1000, 9999);

        // ✅ Update or insert new OTP
        DB::table('otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'updated_at' => now(),
                'created_at' => now()
            ]
        );

        $preferredMethod = $request->method;
        $otpSentVia = 'failed';

        if ($preferredMethod === 'whatsapp' && !empty($user->phone)) {
            $whatsappSent = $this->sendWhatsAppOtp($user->phone, $otp);
            if ($whatsappSent) {
                $otpSentVia = 'whatsapp';
            } else {
                // Fallback to email
                try {
                    $user->notify(new SendOtpNotification($otp));
                    $otpSentVia = 'email';
                } catch (\Exception $e) {
                    Log::error('Failed to send OTP via email after WhatsApp failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } elseif ($preferredMethod === 'email' || empty($user->phone)) {
            // Send via email directly
            try {
                $user->notify(new SendOtpNotification($otp));
                $otpSentVia = 'email';
            } catch (\Exception $e) {
                Log::error('Failed to send OTP via email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            // No preference: use fallback (whatsapp then email)
            $otpSentVia = $this->sendOtpWithFallback($user, $otp);
        }

        if ($otpSentVia === 'failed') {
            return response()->json([
                'error' => 'Failed to send OTP. Please try again later.'
            ], 500);
        }

        Log::info('New OTP resent', [
            'user_id' => $user->id,
            'otp_code' => $otp,
            'method' => $otpSentVia,
            'requested_method' => $preferredMethod
        ]);

        return response()->json([
            'message' => 'A new OTP has been sent',
            'otp_sent_via' => $otpSentVia,
            'user_id' => $user->id
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/resend-otp-email",
     *     summary="Resend OTP code via email only",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent via email successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP code has been resent to your email"),
     *             @OA\Property(property="otp_sent_via", type="string", example="email"),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="email", type="string", example="john.doe@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found or no active OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No active OTP found. Please request a new login.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Please wait before requesting another OTP")
     *         )
     *     )
     * )
     */
    public function resendOtpEmail(Request $request)
    {
        $request->validate([
            'login' => 'required|string'
        ]);

        // Find user by login (email or phone)
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (empty($user->email)) {
            return response()->json(['error' => 'User has no email address'], 400);
        }

        // ✅ Generate a new OTP code
        $otp = rand(1000, 9999);

        // ✅ Update or create OTP record
        DB::table('otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'updated_at' => now(),
                'created_at' => now(), // facultatif si jamais insert
            ]
        );

        // ✅ Send OTP via email
        try {
            $user->notify(new SendOtpNotification($otp));

            Log::info('OTP resent via email', [
                'user_id' => $user->id,
                'otp_code' => $otp,
                'email' => $user->email
            ]);

            return response()->json([
                'message' => 'A new OTP has been sent to your email',
                'otp_sent_via' => 'email',
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send OTP via email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to send OTP via email. Please try again later.'
            ], 500);
        }
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

        // ✅ Extract country & continent
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // Authentifier l'utilisateur avec les claims personnalisés
        $token = JWTAuth::claims([
            'country' => $country,
            'continent' => $continent,
        ])->fromUser($user);

        $tokenExpiration = now()->addMonth();

        $user->token_expiration = $tokenExpiration;
        $user->save();

        Authentication::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'token_expiration' => $tokenExpiration,
                'is_online' => true,
                'connection_date' => now(),
            ]
        );

        return response()->json([
            'user' => $user,
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

    // reset password

    /**
     * @OA\Post(
     *     path="/api/forgot-password",
     *     summary="Send password reset OTP",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="method", type="string", enum={"whatsapp", "email"}, example="email", description="Preferred method to send reset code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password reset code has been sent"),
     *             @OA\Property(property="reset_sent_via", type="string", example="email"),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="email", type="string", example="john.doe@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="User is inactive",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="User account is inactive")
     *         )
     *     )
     * )
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'method' => 'nullable|string|in:whatsapp,email'
        ]);

        // Find user by login (email or phone)
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'User account is inactive'], 403);
        }

        // Generate password reset OTP
        $resetCode = rand(1000, 9999);

        // Store reset code in password_resets table (create this table if it doesn't exist)
        DB::table('password_resets')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code' => $resetCode,
                'expires_at' => now()->addMinutes(15), // 15 minutes expiry for password reset
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $preferredMethod = $request->method;
        $resetSentVia = 'failed';

        if ($preferredMethod === 'whatsapp' && !empty($user->phone)) {
            $whatsappSent = $this->sendWhatsAppPasswordReset($user->phone, $resetCode);
            if ($whatsappSent) {
                $resetSentVia = 'whatsapp';
            } else {
                // Fallback to email
                try {
                    $this->sendPasswordResetEmail($user, $resetCode);
                    $resetSentVia = 'email';
                } catch (\Exception $e) {
                    Log::error('Failed to send password reset via email after WhatsApp failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } elseif ($preferredMethod === 'email' || empty($user->phone)) {
            // Send via email directly
            try {
                $this->sendPasswordResetEmail($user, $resetCode);
                $resetSentVia = 'email';
            } catch (\Exception $e) {
                Log::error('Failed to send password reset via email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            // No preference: use fallback
            $resetSentVia = $this->sendPasswordResetWithFallback($user, $resetCode);
        }

        if ($resetSentVia === 'failed') {
            return response()->json([
                'error' => 'Failed to send password reset code. Please try again later.'
            ], 500);
        }

        Log::info('Password reset code sent', [
            'user_id' => $user->id,
            'reset_code' => $resetCode,
            'method' => $resetSentVia
        ]);

        return response()->json([
            'message' => 'Password reset code has been sent',
            'reset_sent_via' => $resetSentVia,
            'user_id' => $user->id,
            'email' => $user->email ?? null
        ]);
    }

    /**
     * Send password reset via WhatsApp first, fallback to email if WhatsApp fails
     */
    private function sendPasswordResetWithFallback(User $user, $resetCode)
    {
        // First, try WhatsApp if user has phone number
        if (!empty($user->phone)) {
            $whatsappSent = $this->sendWhatsAppPasswordReset($user->phone, $resetCode);

            if ($whatsappSent) {
                Log::info('Password reset sent via WhatsApp', [
                    'user_id' => $user->id,
                    'phone' => $user->phone
                ]);
                return 'whatsapp';
            }
        }

        // Fallback to email
        try {
            $this->sendPasswordResetEmail($user, $resetCode);
            Log::info('Password reset sent via Email (WhatsApp fallback)', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => empty($user->phone) ? 'no_phone' : 'whatsapp_failed'
            ]);
            return 'email';
        } catch (\Exception $e) {
            Log::error('Failed to send password reset via both WhatsApp and Email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return 'failed';
        }
    }

    /**
     * Send password reset via WhatsApp
     */
    private function sendWhatsAppPasswordReset($phone, $resetCode)
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phone);

            $payload = [
                'phonenumber' => '+' . $phoneNumber,
                'text' => "🔐 Your password reset code from dabapp.co is: {$resetCode}\n\nThis code expires in 15 minutes.\nNever share this code with anyone.\n\nIf you didn't request this, please ignore this message."
            ];

            Log::info('Attempting WhatsApp password reset send', [
                'phone' => $phoneNumber,
                'formatted_phone' => '+' . $phoneNumber
            ]);

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer {$this->whatsappApiToken}",
                'Content-Type' => 'application/json',
            ])->post($this->whatsappApiUrl, $payload);

            Log::info('WhatsApp Password Reset API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp password reset send failed', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send password reset via email
     */
    private function sendPasswordResetEmail(User $user, $resetCode)
    {
        // You can create a specific notification for password reset
        // For now, using a simple mail approach
        $user->notify(new \App\Notifications\PasswordResetNotification($resetCode));
    }

    /**
     * @OA\Post(
     *     path="/api/reset-password",
     *     summary="Reset password using OTP code",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login", "code", "password", "password_confirmation"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="code", type="string", example="1234"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password has been reset successfully"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe")
     *             ),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLC..."),
     *             @OA\Property(property="token_expiration", type="string", example="2025-04-17 10:45:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired reset code",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid or expired reset code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="The password confirmation does not match.")
     *         )
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Find user by login (email or phone)
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Verify reset code
        $resetRecord = DB::table('password_resets')
            ->where('user_id', $user->id)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetRecord) {
            return response()->json(['error' => 'Invalid or expired reset code'], 401);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset code after successful use
        DB::table('password_resets')->where('id', $resetRecord->id)->delete();

        // Also update Firebase password if needed
        try {
            $auth = (new Factory)
                ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
                ->createAuth();

            $auth->updateUser($user->email, [
                'password' => $request->password,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update Firebase password', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            // Continue anyway, local password is updated
        }

        // ✅ Extract country & continent
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // Generate new token for automatic login
        $token = JWTAuth::claims([
            'country' => $country,
            'continent' => $continent,
        ])->fromUser($user);

        $tokenExpiration = now()->addMonth();

        // Update authentication record
        Authentication::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'token_expiration' => $tokenExpiration,
                'is_online' => true,
                'connection_date' => now(),
            ]
        );

        Log::info('Password reset successfully', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'message' => 'Password has been reset successfully',
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
            'token' => $token,
            'token_expiration' => $tokenExpiration,
            'country' => $country,
            'continent' => $continent
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/change-password",
     *     summary="Change current password",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword123"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password has been changed successfully"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Current password is incorrect",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Current password is incorrect")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="The password confirmation does not match.")
     *         )
     *     )
     * )
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 401);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Also update Firebase password if needed
        try {
            $auth = (new Factory)
                ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
                ->createAuth();

            $auth->updateUser($user->email, [
                'password' => $request->password,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update Firebase password during change', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            // Continue anyway, local password is updated
        }

        Log::info('Password changed successfully', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'message' => 'Password has been changed successfully',
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone'])
        ]);
    }
}
