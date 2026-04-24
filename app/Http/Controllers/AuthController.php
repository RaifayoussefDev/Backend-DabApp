<?php

namespace App\Http\Controllers;

use App\Helpers\CountryHelper;
use App\Models\Authentication;
use App\Models\NotificationToken;
use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use App\Notifications\DeletionOtpNotification;
use App\Notifications\AccountDeletionStatusNotification;
use App\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Str;
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
    private $whatsappApiUrl   = 'https://api.360messenger.com/v2/sendMessage';
    private $whatsappApiToken = 'lEv2uJJUFIZl9houMUQtkCQzgyWepWEzywf';

    public function __construct()
    {
    }

    private const ACCESS_TOKEN_DURATION = 60;
    private const REFRESH_TOKEN_DURATION = 43200;

    private function generateTokens(User $user, $country = 'Unknown', $continent = 'Unknown')
    {
        $customClaims = [
            'country' => $country,
            'continent' => $continent,
            'type' => 'access'
        ];

        JWTAuth::factory()->setTTL(self::ACCESS_TOKEN_DURATION);
        $accessToken = JWTAuth::claims($customClaims)->fromUser($user);
        $accessTokenExpiration = now()->addMinutes(self::ACCESS_TOKEN_DURATION);

        $refreshToken = Str::random(64);
        $refreshTokenExpiration = now()->addMinutes(self::REFRESH_TOKEN_DURATION);

        // If multi-device is NOT allowed, delete existing sessions
        if (!$user->allow_multi_device) {
            Authentication::where('user_id', $user->id)->delete();
        }

        Authentication::create([
            'user_id' => $user->id,
            'token' => $accessToken,
            'token_expiration' => $accessTokenExpiration,
            'refresh_token' => $refreshToken,
            'refresh_token_expiration' => $refreshTokenExpiration,
            'is_online' => true,
            'connection_date' => now(),
        ]);

        return [
            'token' => $accessToken,
            'token_expiration' => $accessTokenExpiration,
            'refresh_token' => $refreshToken,
            'refresh_token_expiration' => $refreshTokenExpiration,
        ];
    }

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
     *         response=202,
     *         description="Registration successful, OTP required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registration successful, OTP required for verification"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="requiresOTP", type="boolean", example=true),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="otp_sent_via", type="string", example="whatsapp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Le numéro doit commencer par +
                    if (!str_starts_with($value, '+')) {
                        $fail('Phone number must include country code (e.g., +212...)');
                    }

                    // Vérifier le format : + suivi de 10 à 15 chiffres
                    if (!preg_match('/^\+\d{10,15}$/', $value)) {
                        $fail('Invalid phone number format. Must be: +[country code][number]');
                    }
                }
            ],
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Get country from proxy headers
        $countryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Morocco';

        // ✅ Récupérer country_id et infos depuis la base de données
        $countryData = CountryHelper::processCountryAndPhoneByName($request->phone, $countryName);

        $formattedPhone = $countryData['formatted_phone'] ?? $request->phone;

        Log::info('Registration attempt', [
            'original_phone' => $request->phone,
            'country_name' => $countryName,
            'formatted_phone' => $formattedPhone,
            'email' => $request->email,
        ]);

        // Check if email exists
        $existingUserByEmail = User::where('email', $request->email)->first();

        if ($existingUserByEmail) {
            // Si l'utilisateur existe MAIS n'a PAS terminé son inscription (OTP)
            if (!$existingUserByEmail->is_registration_completed) {
                Log::info('Found unverified user (registration not completed), allowing update', [
                    'user_id' => $existingUserByEmail->id,
                    'old_phone' => $existingUserByEmail->phone,
                    'new_phone' => $formattedPhone,
                ]);

                // Vérifier si le nouveau numéro n'est pas utilisé par un AUTRE utilisateur dont l'inscription est terminée
                $phoneUsedByOther = User::where('phone', $formattedPhone)
                    ->where('id', '!=', $existingUserByEmail->id)
                    ->where('is_registration_completed', true)
                    ->exists();

                if ($phoneUsedByOther) {
                    return response()->json([
                        'error' => 'Phone number already in use',
                        'message' => 'This phone number is already registered with another verified account'
                    ], 422);
                }

                // Mettre à jour les informations de l'utilisateur non vérifié
                $existingUserByEmail->update([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'phone' => $formattedPhone,
                    'password' => Hash::make($request->password),
                    'country_id' => $countryData['country_id'],
                    'updated_at' => now(),
                ]);

                $user = $existingUserByEmail;

                Log::info('Updated unverified user information', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'new_phone' => $user->phone
                ]);
            } else {
                // L'utilisateur existe ET est vérifié - erreur classique
                Log::warning('Email already exists with verified account', [
                    'email' => $request->email,
                    'existing_user_id' => $existingUserByEmail->id
                ]);

                return response()->json([
                    'error' => 'Email already exists',
                    'message' => 'This email is already registered. Please login or use password reset.'
                ], 422);
            }
        } else {
            // Vérifier si le téléphone existe déjà (peu importe le statut d'inscription)
            $existingUserByPhone = User::where('phone', $formattedPhone)->first();

            if ($existingUserByPhone) {
                if ($existingUserByPhone->is_registration_completed) {
                    // Phone utilisé par un compte complété → bloquer
                    Log::warning('Phone number already exists with completed account', [
                        'formatted_phone' => $formattedPhone,
                        'existing_user_id' => $existingUserByPhone->id,
                    ]);

                    return response()->json([
                        'error' => 'Phone number already exists',
                        'phone' => $formattedPhone,
                        'message' => 'This phone number is already registered with a verified account'
                    ], 422);
                } else {
                    // Phone utilisé par un compte non complété avec un autre email → bloquer aussi
                    Log::warning('Phone number already used by another unverified account', [
                        'formatted_phone' => $formattedPhone,
                        'existing_user_id' => $existingUserByPhone->id,
                    ]);

                    return response()->json([
                        'error' => 'Phone number already in use',
                        'message' => 'This phone number is already associated with another account'
                    ], 422);
                }
            }

            // Créer un NOUVEAU utilisateur non vérifié
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $formattedPhone,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id ?? 1,
                'verified' => false,
                'is_active' => false,
                'is_online' => false,
                'language' => 'en',
                'timezone' => 'Africa/Casablanca',
                'two_factor_enabled' => true,
                'country_id' => $countryData['country_id'],
            ]);

            // ✅ Create default Notification Preferences
            \App\Models\NotificationPreference::create([
                'user_id' => $user->id,
                'listing_approved' => true,
                'listing_rejected' => true,
                'listing_sold' => true,
                'bid_placed' => true,
                'bid_accepted' => true,
                'bid_rejected' => true,
                'bid_outbid' => true,
                'soom_new_negotiation' => true, // Important for Soom
                'soom_accepted' => true,
                'soom_rejected' => true,
                'dealer_approved' => true, // Important for Dealer
                'system_updates' => true,
                'push_enabled' => true,
                'email_enabled' => true,
                // Add other Defaults as needed, mostly true is good for engagement
            ]);

            Log::info('New user created (unverified)', [
                'user_id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone
            ]);
        }

        // Generate and send OTP
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

        // Send OTP to both WhatsApp and email based on config
        $otpSentVia = $this->sendOtpDynamic($user, $otp);

        if ($otpSentVia === 'failed') {
            Log::error('Failed to send OTP during registration', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email
            ]);

            return response()->json([
                'error' => 'Failed to send OTP. Please try again.',
                'user_id' => $user->id
            ], 500);
        }

        // OTP skipped (both channels disabled) → activate user and return token immediately
        if ($otpSentVia === 'skipped') {
            Log::info('OTP skipped during registration (all channels disabled)', [
                'user_id' => $user->id,
            ]);

            $user->is_registration_completed = true;
            $user->is_active = true;
            $user->is_online = true;
            $user->last_login = now();
            $user->save();

            $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
            $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';
            $tokens = $this->generateTokens($user, $country, $continent);

            $user->refresh();

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $tokens['token'],
                'token_expiration' => $tokens['token_expiration'],
                'refresh_token' => $tokens['refresh_token'],
                'refresh_token_expiration' => $tokens['refresh_token_expiration'],
                'country' => $countryData['country_name'],
                'country_code' => $countryData['country_code'],
                'country_id' => $countryData['country_id'],
                'formatted_phone' => $formattedPhone,
                'otp_sent_via' => $otpSentVia,
            ], 200);
        }

        // OTP sent → user must verify before getting token
        return response()->json([
            'message' => 'Registration successful, OTP required for verification',
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
            'requiresOTP' => true,
            'user_id' => $user->id,
            'country' => $countryData['country_name'],
            'country_code' => $countryData['country_code'],
            'country_id' => $countryData['country_id'],
            'formatted_phone' => $formattedPhone,
            'otp_sent_via' => $otpSentVia,
        ], 202);
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="User login",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login","password"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com or +123456789"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful login without 2FA",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="token_expiration", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Login with OTP required",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP required"),
     *             @OA\Property(property="requiresOTP", type="boolean", example=true),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="otp_sent_via", type="string", example="whatsapp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Si c'est un email, on passe
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }

                    // Si c'est un numéro de téléphone, il doit commencer par +
                    if (!str_starts_with($value, '+')) {
                        $fail('Phone number must include country code (e.g., +212...)');
                    }

                    // Vérifier que c'est bien un format de téléphone valide (+ suivi de chiffres)
                    if (!preg_match('/^\+\d{10,15}$/', $value)) {
                        $fail('Invalid phone number format. Must be: +[country code][number]');
                    }
                }
            ],
            'password' => 'required|string'
        ]);

        $login = $request->input('login');
        $password = $request->input('password');
        $reactivationMessage = null;

        // ⭐ DÉTERMINER LE TYPE DE LOGIN DÈS LE DÉBUT
        $isEmailLogin = filter_var($login, FILTER_VALIDATE_EMAIL);

        Log::info('Login attempt', [
            'login' => $login,
            'is_email_login' => $isEmailLogin
        ]);

        // Find user by email or phone
        $user = null;

        if ($isEmailLogin) {
            // Login is an email
            $user = User::withTrashed()->where('email', $login)->first();
            Log::info('Login attempt with email', ['email' => $login]);
        } else {
            // Login is potentially a phone number - try direct match or with/without + prefix
            $countryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Morocco';

            // Normalize phone: remove trunk prefix 0 after country code (e.g. +2120xxx → +212xxx)
            $normalizedLogin = CountryHelper::formatPhoneByCountryName($login, $countryName);

            // First, try normalized phone (new accounts stored with correct format)
            $user = User::withTrashed()->where('phone', $normalizedLogin)->first();

            // Fallback: try original phone (old accounts stored with wrong format)
            if (!$user && $normalizedLogin !== $login) {
                $user = User::withTrashed()->where('phone', $login)->first();
            }

            if (!$user) {
                // Try removing/adding + prefix
                if (str_starts_with($normalizedLogin, '+')) {
                    $phoneWithoutPlus = substr($normalizedLogin, 1);
                    $user = User::withTrashed()->where('phone', $phoneWithoutPlus)->first();
                } else {
                    $phoneWithPlus = '+' . $normalizedLogin;
                    $user = User::withTrashed()->where('phone', $phoneWithPlus)->first();
                }
            }

            Log::info('Login attempt with phone', [
                'phone' => $login,
                'normalized_phone' => $normalizedLogin,
                'user_found' => $user ? true : false
            ]);
        }

        // ✅ On garde la détection du pays pour le retour dans la réponse
        $countryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Morocco';

        if (!$user || !Hash::check($password, $user->password)) {
            Log::warning('Login failed - invalid credentials', [
                'login' => $login,
                'user_found' => $user ? true : false
            ]);
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // ⭐ HANDLE SOFT DELETED USER (30-day reactivation)
        if ($user->trashed()) {
            $daysSinceDeletion = $user->deleted_at->diffInDays(now());

            if ($daysSinceDeletion > 30) {
                Log::warning('Login failed - account permanently deleted (past 30 days)', [
                    'user_id' => $user->id,
                    'deleted_at' => $user->deleted_at
                ]);
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            // Restore account
            $user->restore();
            Log::info('Account reactivated on login', ['user_id' => $user->id]);

            // ✅ SEND REACTIVATION NOTIFICATIONS
            try {
                // Email
                $user->notify(new \App\Notifications\AccountReactivationNotification());

                // WhatsApp
                if (!empty($user->phone)) {
                    $this->sendReactivationWhatsApp($user->phone, $user->first_name);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send reactivation notifications', ['error' => $e->getMessage()]);
            }

            // Note: We continue the login flow after restoration
            $reactivationMessage = "Your account has been reactivated / تم إعادة تفعيل حسابك";
        }

        // ✅ VÉRIFIER SI L'INSCRIPTION EST COMPLÈTE (OTP/EMAIL)
        if (!$user->is_registration_completed) {
            Log::warning('Login attempt by user with incomplete registration', [
                'user_id' => $user->id,
                'email' => $user->email,
                'login_type' => $isEmailLogin ? 'email' : 'phone'
            ]);

            // Générer et envoyer OTP pour permettre la vérification
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

            // ⭐ ENVOYER OTP SELON LE TYPE DE LOGIN
            $otpSentVia = $this->sendOtpBasedOnLoginMethod($user, $otp, $isEmailLogin);

            Log::info('OTP sent for incomplete registration user', [
                'user_id' => $user->id,
                'otp_sent_via' => $otpSentVia,
                'login_type' => $isEmailLogin ? 'email' : 'phone'
            ]);

            return response()->json([
                'error' => 'Account not verified', // Keeping generic error for frontend
                'message' => 'Please verify your account with the OTP code we just sent',
                'requiresOTP' => true,
                'user_id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'otp_sent_via' => $otpSentVia
            ], 403);
        }

        if (!$user->is_active) {
            Log::warning('Login failed - user inactive', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return response()->json(['error' => 'User is inactive'], 403);
        }

        $user->is_online = 1;
        $user->last_login = now();
        $user->save();

        Log::info('User logged in successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'login_method' => $isEmailLogin ? 'email' : 'phone'
        ]);

        // Extract country & continent from proxy headers
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

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

            // ⭐ ENVOYER OTP 2FA SELON LE TYPE DE LOGIN
            $otpSentVia = $this->sendOtpBasedOnLoginMethod($user, $otp, $isEmailLogin);

            if ($otpSentVia === 'failed') {
                Log::error('Failed to send OTP during login', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'phone' => $user->phone
                ]);

                return response()->json([
                    'error' => 'Unable to send OTP code. Please try again later.',
                    'user_id' => $user->id
                ], 500);
            }

            Log::info('2FA OTP sent', [
                'user_id' => $user->id,
                'otp_sent_via' => $otpSentVia,
                'login_type' => $isEmailLogin ? 'email' : 'phone'
            ]);

            return response()->json([
                'message' => 'OTP required',
                'user_id' => $user->id,
                'requiresOTP' => true,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'country' => $countryName,
                'otp_sent_via' => $otpSentVia
            ], 202);
        }

        // ✅ GÉNÉRER LES DEUX TOKENS
        $tokens = $this->generateTokens($user, $country, $continent);

        return response()->json([
            'user' => $user,
            'token' => $tokens['token'],
            'token_expiration' => $tokens['token_expiration'],
            'refresh_token' => $tokens['refresh_token'],
            'refresh_token_expiration' => $tokens['refresh_token_expiration'],
            'country' => $country,
            'continent' => $continent
        ]);
    }

    /**
     * Envoie OTP selon la méthode de login (email → email, phone → WhatsApp)
     */
    private function sendOtpBasedOnLoginMethod(User $user, $otp, $isEmailLogin)
    {
        // ⭐ Toujours prioriser WhatsApp même si le login est par email
        Log::info('OTP delivery: prioritizing WhatsApp', [
            'user_id' => $user->id,
            'login_method' => $isEmailLogin ? 'email' : 'phone',
            'has_phone' => !empty($user->phone)
        ]);

        return $this->sendOtpWithWhatsAppFirst($user, $otp);
    }
    private function sendOtpDynamic(User $user, $otp): string
    {
        $whatsappEnabled = config('services.otp.whatsapp_enabled', true);
        $emailEnabled    = config('services.otp.email_enabled', true);

        // Both channels disabled → OTP sending is intentionally skipped
        if (!$whatsappEnabled && !$emailEnabled) {
            Log::info('OTP dynamic: all channels disabled, skipping', ['user_id' => $user->id]);
            return 'skipped';
        }

        $whatsappOk = false;
        $emailOk    = false;

        if ($whatsappEnabled && !empty($user->phone)) {
            $whatsappOk = $this->sendWhatsAppOtp($user->phone, $otp);
            Log::info('OTP dynamic: WhatsApp attempt', [
                'user_id' => $user->id,
                'success' => $whatsappOk,
            ]);
        }

        if ($emailEnabled && !empty($user->email)) {
            try {
                $user->notify(new SendOtpNotification($otp));
                $emailOk = true;
                Log::info('OTP dynamic: Email sent', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                Log::error('OTP dynamic: Email failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if ($whatsappOk && $emailOk) return 'both';
        if ($whatsappOk)             return 'whatsapp';
        if ($emailOk)                return 'email';

        return 'failed';
    }

    private function sendOtpWithWhatsAppFirst(User $user, $otp)
    {
        // Try WhatsApp first if user has phone number
        if (!empty($user->phone)) {
            $whatsappSent = $this->sendWhatsAppOtp($user->phone, $otp);

            if ($whatsappSent) {
                Log::info('OTP sent via WhatsApp', [
                    'user_id' => $user->id,
                    'phone' => $user->phone
                ]);
                return 'whatsapp';
            } else {
                Log::info('WhatsApp OTP failed, trying email fallback', [
                    'user_id' => $user->id,
                    'phone' => $user->phone
                ]);
            }
        }

        // Fallback to email if WhatsApp failed or no phone
        try {
            if (empty($user->email)) {
                throw new \Exception('User has no email address');
            }

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
     * Send OTP via WhatsApp first, fallback to email if WhatsApp fails
     */
    private function sendOtpWithFallback(User $user, $otp)
    {
        // Try WhatsApp first if user has phone number
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
            if (empty($user->email)) {
                throw new \Exception('User has no email address');
            }

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
                'text'        => "🔐 رمز التحقق الخاص بك على DabApp:\n\n*{$otp}*\n\n⏳ صالح لمدة 5 دقائق فقط.\n🔒 لا تشارك هذا الرمز مع أحد.",
            ];

            Log::info('Attempting WhatsApp OTP send via 360messenger', [
                'phone' => '+' . $phoneNumber,
            ]);

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer {$this->whatsappApiToken}",
                'Content-Type'  => 'application/json',
            ])->post($this->whatsappApiUrl, $payload);

            Log::info('360messenger API Response', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp OTP send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Format phone number
     */
    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Find user by login (email or phone) with flexible phone matching
     */
    private function findUserByLogin($login)
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            // Login is an email
            return User::where('email', $login)->first();
        } else {
            // Normalize phone: remove trunk prefix 0 after country code (e.g. +2120xxx → +212xxx)
            $countryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Morocco';
            $normalizedLogin = \App\Helpers\CountryHelper::formatPhoneByCountryName($login, $countryName);

            // Try normalized phone first (new accounts)
            $user = User::where('phone', $normalizedLogin)->first();

            // Fallback: try original (old accounts stored with wrong format)
            if (!$user && $normalizedLogin !== $login) {
                $user = User::where('phone', $login)->first();
            }

            if (!$user) {
                // Try removing/adding + prefix (for database compatibility)
                if (str_starts_with($normalizedLogin, '+')) {
                    $phoneWithoutPlus = substr($normalizedLogin, 1);
                    $user = User::where('phone', $phoneWithoutPlus)->first();
                } else {
                    $phoneWithPlus = '+' . $normalizedLogin;
                    $user = User::where('phone', $phoneWithPlus)->first();
                }
            }

            return $user;
        }
    }

    /**
     * @OA\Post(
     *     path="/api/resend-otp",
     *     summary="Resend OTP code",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com or +123456789"),
     *             @OA\Property(property="method", type="string", enum={"whatsapp", "email"}, example="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully"
     *     )
     * )
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'login' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Si c'est un email, on passe
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }

                    // Si c'est un numéro de téléphone, il DOIT commencer par +
                    if (!str_starts_with($value, '+')) {
                        $fail('Phone number must include country code (e.g., +212...)');
                    }

                    // Vérifier le format valide
                    if (!preg_match('/^\+\d{10,15}$/', $value)) {
                        $fail('Invalid phone number format. Must be: +[country code][number]');
                    }
                }
            ],
            'method' => 'nullable|string|in:whatsapp,email'
        ]);

        // Rate limiting
        $cacheKey = 'otp_resend_' . md5($request->login);
        $lastRequest = Cache::get($cacheKey);

        if ($lastRequest && now()->diffInSeconds($lastRequest) < 60) {
            return response()->json([
                'error' => 'Please wait before requesting another OTP'
            ], 429);
        }

        // Find user with flexible phone matching
        $user = $this->findUserByLogin($request->login);

        if (!$user) {
            Log::warning('User not found for resend OTP', [
                'login' => $request->login,
                'login_type' => filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone'
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        Log::info('User found for resend OTP', [
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'login_attempted' => $request->login
        ]);

        // Generate new OTP
        $otp = rand(1000, 9999);

        DB::table('otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'updated_at' => now(),
                'created_at' => now()
            ]
        );

        // Set rate limiting
        Cache::put($cacheKey, now(), 60);

        $preferredMethod = $request->method;
        $otpSentVia = 'failed';

        if ($preferredMethod === 'whatsapp' && !empty($user->phone)) {
            // User specifically requested WhatsApp
            $whatsappSent = $this->sendWhatsAppOtp($user->phone, $otp);
            if ($whatsappSent) {
                $otpSentVia = 'whatsapp';
            } else {
                // Fallback to email if WhatsApp fails
                try {
                    if (!empty($user->email)) {
                        $user->notify(new SendOtpNotification($otp));
                        $otpSentVia = 'email';
                        Log::info('Resend OTP via email (WhatsApp fallback)', [
                            'user_id' => $user->id,
                            'email' => $user->email
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send OTP via email after WhatsApp failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } elseif ($preferredMethod === 'email' && !empty($user->email)) {
            // User specifically requested email
            try {
                $user->notify(new SendOtpNotification($otp));
                $otpSentVia = 'email';
            } catch (\Exception $e) {
                Log::error('Failed to send OTP via email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                // Fallback to WhatsApp if email fails
                if (!empty($user->phone)) {
                    $whatsappSent = $this->sendWhatsAppOtp($user->phone, $otp);
                    if ($whatsappSent) {
                        $otpSentVia = 'whatsapp';
                        Log::info('Resend OTP via WhatsApp (email fallback)', [
                            'user_id' => $user->id,
                            'phone' => $user->phone
                        ]);
                    }
                }
            }
        } else {
            // No specific method requested, use WhatsApp first fallback
            $otpSentVia = $this->sendOtpWithWhatsAppFirst($user, $otp);
        }

        if ($otpSentVia === 'failed') {
            return response()->json([
                'error' => 'Failed to send OTP. Please try again later.'
            ], 500);
        }

        Log::info('OTP resent', [
            'user_id' => $user->id,
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
     *     summary="Resend OTP via email only",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com or +123456789")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent via email successfully"
     *     )
     * )
     */
    public function resendOtpEmail(Request $request)
    {
        $request->validate([
            'login' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Si c'est un email, on passe
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }

                    // Si c'est un numéro de téléphone, il DOIT commencer par +
                    if (!str_starts_with($value, '+')) {
                        $fail('Phone number must include country code (e.g., +212...)');
                    }

                    // Vérifier le format valide
                    if (!preg_match('/^\+\d{10,15}$/', $value)) {
                        $fail('Invalid phone number format. Must be: +[country code][number]');
                    }
                }
            ]
        ]);

        // Rate limiting
        $cacheKey = 'otp_email_resend_' . md5($request->login);
        $lastRequest = Cache::get($cacheKey);

        if ($lastRequest && now()->diffInSeconds($lastRequest) < 60) {
            return response()->json([
                'error' => 'Please wait before requesting another OTP'
            ], 429);
        }

        // Find user with flexible phone matching
        $user = $this->findUserByLogin($request->login);

        if (!$user) {
            Log::warning('User not found for resend OTP email', [
                'login' => $request->login,
                'login_type' => filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone'
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        if (empty($user->email)) {
            return response()->json(['error' => 'User has no email address'], 400);
        }

        Log::info('User found for resend OTP email', [
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'login_attempted' => $request->login
        ]);

        // Set rate limiting
        Cache::put($cacheKey, now(), 60);

        // Generate new OTP
        $otp = rand(1000, 9999);

        DB::table('otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Use email only for resendOtpEmail endpoint
        try {
            $user->notify(new SendOtpNotification($otp));
            $otpSentVia = 'email';

            Log::info('OTP sent via Email for resendOtpEmail', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send OTP via email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to send OTP via email. Please try again later.'
            ], 500);
        }

        if ($otpSentVia !== 'email') {
            Log::warning('Expected email but got different method', [
                'user_id' => $user->id,
                'expected' => 'email',
                'actual' => $otpSentVia
            ]);
        }

        return response()->json([
            'message' => 'A new OTP has been sent to your email',
            'otp_sent_via' => 'email', // Always return email since that's what this endpoint is for
            'user_id' => $user->id,
            'email' => $user->email
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/verify-otp",
     *     summary="Verify OTP code",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login", "otp"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com or +212695388904", description="User email or phone number"),
     *             @OA\Property(property="otp", type="string", example="1234", description="4-digit OTP code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP valid, authentication successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object", description="User information"),
     *             @OA\Property(property="token", type="string", description="JWT authentication token"),
     *             @OA\Property(property="token_expiration", type="string", format="date-time", description="Token expiration timestamp"),
     *             @OA\Property(property="country", type="string", example="Morocco"),
     *             @OA\Property(property="continent", type="string", example="Africa")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid or expired OTP")
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
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'login' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Si c'est un email, on passe
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }

                    // Si c'est un numéro de téléphone, il doit commencer par +
                    if (!str_starts_with($value, '+')) {
                        $fail('Phone number must include country code (e.g., +212...)');
                    }

                    // Vérifier le format valide
                    if (!preg_match('/^\+\d{10,15}$/', $value)) {
                        $fail('Invalid phone number format. Must be: +[country code][number]');
                    }
                }
            ],
            'otp' => 'required|string|size:4',
        ]);

        // Find user with flexible phone matching
        $user = $this->findUserByLogin($request->login);

        if (!$user) {
            Log::warning('User not found for OTP verification', [
                'login' => $request->login,
                'login_type' => filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone'
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        Log::info('User found for OTP verification', [
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'login_attempted' => $request->login
        ]);

        // Verify OTP
        $otpRecord = DB::table('otps')
            ->where('user_id', $user->id)
            ->where('code', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            Log::warning('Invalid or expired OTP attempt', [
                'user_id' => $user->id,
                'otp_provided' => $request->otp,
                'login' => $request->login
            ]);
            return response()->json(['error' => 'Invalid or expired OTP'], 401);
        }

        // Delete OTP after use
        DB::table('otps')->where('id', $otpRecord->id)->delete();

        Log::info('OTP verified successfully', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        // ✅ ACTIVER ET FINALISER L'INSCRIPTION
        if (!$user->is_registration_completed || !$user->is_active) {
            $user->is_registration_completed = true; // OTP/Registration verified
            $user->is_active = true;
            // NOTE: We do NOT touch 'verified' here anymore. 'verified' is only for Identity Verification (Blue Tick).
            $user->save();

            Log::info('User registration completed and activated', [
                'user_id' => $user->id,
                'was_registration_completed' => $user->wasChanged('is_registration_completed'),
                'was_activated' => $user->wasChanged('is_active')
            ]);

            // ✅ SEND ACTIVATION NOTIFICATIONS
            try {
                // Email
                $user->notify(new \App\Notifications\AccountReactivationNotification()); // Or create AccountActivatedNotification if different text needed, reusing Reactivation for now as user said "compte et activer"

                // WhatsApp
                if (!empty($user->phone)) {
                    $this->sendActivationWhatsApp($user->phone, $user->first_name);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send activation notifications', ['error' => $e->getMessage()]);
            }
        }

        // Extract country & continent
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // ✅ GÉNÉRER LES DEUX TOKENS
        $tokens = $this->generateTokens($user, $country, $continent);

        // ✅ METTRE À JOUR is_online et last_login
        $user->is_online = true;
        $user->last_login = now();
        $user->save();

        Log::info('User authentication completed after OTP verification', [
            'user_id' => $user->id
        ]);

        // ✅ RECHARGER L'UTILISATEUR pour avoir les données fraîches
        $user->refresh();

        return response()->json([
            'user' => $user,
            'token' => $tokens['token'],
            'token_expiration' => $tokens['token_expiration'],
            'refresh_token' => $tokens['refresh_token'],
            'refresh_token_expiration' => $tokens['refresh_token_expiration'],
            'country' => $country,
            'continent' => $continent
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     summary="Get authenticated user information",
     *     tags={"Authentification"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object", description="Full user object with relations"),
     *             @OA\Property(property="helper", type="object",
     *                 description="Helper profile summary. `is_helper: false` if the user has not registered as a helper.",
     *                 @OA\Property(property="is_helper",         type="boolean", example=true),
     *                 @OA\Property(property="is_verified",       type="boolean", example=false,
     *                     description="Set by admin after reviewing the profile"),
     *                 @OA\Property(property="is_available",      type="boolean", example=false),
     *                 @OA\Property(property="level",             type="string",  enum={"standard","elite","vanguard"}, example="standard"),
     *                 @OA\Property(property="rating",            type="number",  format="float", example=4.80),
     *                 @OA\Property(property="total_assists",     type="integer", example=12),
     *                 @OA\Property(property="service_radius_km", type="integer", example=25),
     *                 @OA\Property(property="terms_accepted_at", type="string",  format="date-time", nullable=true,
     *                     example="2026-04-20T10:00:00.000000Z"),
     *                 @OA\Property(property="expertise_types", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id",      type="integer", example=1),
     *                         @OA\Property(property="name",    type="string",  example="tire_repair"),
     *                         @OA\Property(property="name_en", type="string",  example="Tire Repair"),
     *                         @OA\Property(property="name_ar", type="string",  example="إصلاح الإطارات"),
     *                         @OA\Property(property="icon",    type="string",  example="tire_repair")
     *                     )
     *                 )
     *             )
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
            'bankCards',
            'pointsOfInterest',
        ])->find($user->id);

        // Decrypt CVV
        foreach ($userWithData->bankCards as $card) {
            if (!empty($card->cvv)) {
                try {
                    $card->cvv = decrypt($card->cvv);
                } catch (\Exception $e) {
                    $card->cvv = null;
                }
            }
        }

        // Helper profile summary
        $helperProfile = \App\Models\Assist\HelperProfile::where('user_id', $user->id)
            ->with('expertiseTypes')
            ->first();

        $helperData = null;
        if ($helperProfile) {
            $helperData = [
                'is_helper'         => true,
                'status'            => $helperProfile->status,
                'is_verified'       => $helperProfile->is_verified,
                'is_available'      => $helperProfile->is_available,
                'level'             => $helperProfile->level,
                'rating'            => $helperProfile->rating,
                'total_assists'     => $helperProfile->total_assists,
                'service_radius_km' => $helperProfile->service_radius_km,
                'terms_accepted_at' => $helperProfile->terms_accepted_at,
                'expertise_types'   => $helperProfile->expertiseTypes->map(fn($e) => [
                    'id'      => $e->id,
                    'name'    => $e->name,
                    'name_en' => $e->name_en,
                    'name_ar' => $e->name_ar,
                    'icon'    => $e->icon,
                ]),
            ];
        }

        return response()->json([
            'user'   => $userWithData,
            'helper' => $helperData ?? ['is_helper' => false],
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="User logout",
     *     description="Logout user and deactivate FCM token for push notifications",
     *     tags={"Authentification"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="fcm_token",
     *                 type="string",
     *                 description="Firebase Cloud Messaging token to deactivate (optional). If not provided, all user tokens will be deactivated.",
     *                 example="fX7YkR4dT9eB2Hf5Jk8LmN3Pq1Rs6Tv0..."
     *             ),
     *             @OA\Property(
     *                 property="logout_all_devices",
     *                 type="boolean",
     *                 description="If true, deactivate all FCM tokens for this user",
     *                 example=false
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully logged out"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="tokens_deactivated", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $user = auth()->user();
        $tokensDeactivated = 0;

        if ($user) {
            // Si un fcm_token est fourni, désactiver celui-là uniquement
            if ($request->filled('fcm_token')) {
                $tokensDeactivated = NotificationToken::where('user_id', $user->id)
                    ->where('fcm_token', $request->fcm_token)
                    ->update(['is_active' => false]);
            }
            // Si logout_all_devices = true, tout désactiver
            elseif ($request->boolean('logout_all_devices')) {
                $tokensDeactivated = NotificationToken::where('user_id', $user->id)
                    ->update(['is_active' => false]);
            }
            // SINON: Ne rien faire avec les tokens FCM
            // Le web logout n'affecte pas les apps mobiles ✅
        }

        auth()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
            'data' => [
                'tokens_deactivated' => $tokensDeactivated
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     summary="Refresh JWT access token using refresh token",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", example="abc123...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="New access token generated"
     *     )
     * )
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string'
        ]);

        $refreshToken = $request->input('refresh_token');

        // Chercher l'authentification avec ce refresh token
        $auth = Authentication::where('refresh_token', $refreshToken)
            ->where('refresh_token_expiration', '>', now())
            ->first();

        if (!$auth) {
            Log::warning('Invalid or expired refresh token', [
                'refresh_token' => substr($refreshToken, 0, 10) . '...'
            ]);

            return response()->json([
                'error' => 'Invalid or expired refresh token',
                'message' => 'Please login again'
            ], 401);
        }

        $user = User::find($auth->user_id);

        if (!$user || !$user->is_active) {
            return response()->json(['error' => 'User is inactive'], 403);
        }

        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // ✅ GÉNÉRER NOUVEAU ACCESS TOKEN (on garde le même refresh token)
        $customClaims = [
            'country' => $country,
            'continent' => $continent,
            'type' => 'access'
        ];

        JWTAuth::factory()->setTTL(self::ACCESS_TOKEN_DURATION);
        $accessToken = JWTAuth::claims($customClaims)->fromUser($user);

        $accessTokenExpiration = now()->addMinutes(self::ACCESS_TOKEN_DURATION);

        // Mettre à jour seulement l'access token
        $auth->update([
            'token' => $accessToken,
            'token_expiration' => $accessTokenExpiration,
        ]);

        Log::info('Access token refreshed', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'token' => $accessToken,
            'token_expiration' => $accessTokenExpiration,
            'refresh_token' => $refreshToken,
            'refresh_token_expiration' => $auth->refresh_token_expiration,
            'message' => 'Access token refreshed successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/user/update",
     *     summary="Update user profile",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="birthday", type="string", format="date"),
     *             @OA\Property(property="profile_picture", type="string", description="Path or URL of the profile picture")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|unique:users,phone,' . $user->id,
            'birthday' => 'nullable|date',
            'profile_picture' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'nullable|integer|exists:countries,id',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $request->only(
            'first_name',
            'last_name',
            'email',
            'phone',
            'birthday',
            'profile_picture',
            'gender',
            'address',
            'postal_code',
            'country_id',
            'language',
            'timezone'
        );

        $user->update($data);

        // Refresh user to get updated fields
        $user->refresh();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/user/two-factor-toggle",
     *     summary="Toggle two-factor authentication",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Two-factor authentication toggled"
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

    // Password Reset Methods

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
     *             @OA\Property(property="method", type="string", enum={"whatsapp", "email"}, example="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset OTP sent successfully"
     *     )
     * )
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'login' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Si c'est un email, on passe
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }

                    // Si c'est un numéro de téléphone, il DOIT commencer par +
                    if (!str_starts_with($value, '+')) {
                        $fail('Phone number must include country code (e.g., +212...)');
                    }

                    // Vérifier le format valide
                    if (!preg_match('/^\+\d{10,15}$/', $value)) {
                        $fail('Invalid phone number format. Must be: +[country code][number]');
                    }
                }
            ],
            'method' => 'nullable|string|in:whatsapp,email'
        ]);

        $login = $request->input('login');

        // ⭐ DÉTERMINER LE TYPE DE LOGIN DÈS LE DÉBUT
        $isEmailLogin = filter_var($login, FILTER_VALIDATE_EMAIL);

        Log::info('Password reset attempt', [
            'login' => $login,
            'is_email_login' => $isEmailLogin
        ]);

        // Find user by email or phone
        $user = null;

        if ($isEmailLogin) {
            // Login is an email
            $user = User::where('email', $login)->first();
            Log::info('Password reset attempt with email', ['email' => $login]);
        } else {
            // Login is potentially a phone number - try different formats

            // First, try exact match
            $user = User::where('phone', $login)->first();

            if (!$user) {
                // If no exact match, try with country processing
                $countryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Morocco';

                try {
                    $countryData = CountryHelper::processCountryAndPhoneByName($login, $countryName);
                    $formattedPhone = $countryData['formatted_phone'];

                    Log::info('Trying password reset with formatted phone', [
                        'original' => $login,
                        'formatted' => $formattedPhone,
                        'country' => $countryName
                    ]);

                    $user = User::where('phone', $formattedPhone)->first();
                } catch (\Exception $e) {
                    Log::warning('Failed to format phone for password reset', [
                        'phone' => $login,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (!$user) {
                // Try removing/adding + prefix
                if (str_starts_with($login, '+')) {
                    $phoneWithoutPlus = substr($login, 1);
                    $user = User::where('phone', $phoneWithoutPlus)->first();
                } else {
                    $phoneWithPlus = '+' . $login;
                    $user = User::where('phone', $phoneWithPlus)->first();
                }
            }

            Log::info('Password reset attempt with phone', [
                'phone' => $login,
                'user_found' => $user ? true : false
            ]);
        }

        if (!$user) {
            Log::warning('Password reset failed - user not found', [
                'login' => $login
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        if (!$user->is_active) {
            Log::warning('Password reset failed - user inactive', [
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'User account is inactive'], 403);
        }

        // Generate password reset OTP
        $resetCode = rand(1000, 9999);

        // Store reset code in password_resets table
        DB::table('password_resets')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code' => $resetCode,
                'expires_at' => now()->addMinutes(15),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $preferredMethod = $request->method;
        $resetSentVia = 'failed';

        if ($preferredMethod === 'whatsapp' && !empty($user->phone)) {
            // Try WhatsApp first
            $whatsappSent = $this->sendWhatsAppPasswordReset($user->phone, $resetCode);
            if ($whatsappSent) {
                $resetSentVia = 'whatsapp';
            } else {
                // Fallback to email
                try {
                    if (!empty($user->email)) {
                        $user->notify(new PasswordResetNotification($resetCode));
                        $resetSentVia = 'email';
                        Log::info('Password reset sent via email (WhatsApp fallback)', [
                            'user_id' => $user->id,
                            'email' => $user->email
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send password reset via email after WhatsApp failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            // Send directly by email or use fallback method
            try {
                if (empty($user->email)) {
                    throw new \Exception('User has no email address');
                }

                Log::info('Attempting to send password reset email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'reset_code' => $resetCode
                ]);

                $user->notify(new PasswordResetNotification($resetCode));
                $resetSentVia = 'email';

                Log::info('Password reset email sent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send password reset via email', [
                    'user_id' => $user->id,
                    'email' => $user->email ?? 'none',
                    'error' => $e->getMessage()
                ]);

                // If email preferred but WhatsApp available, try WhatsApp
                if (!empty($user->phone)) {
                    Log::info('Trying WhatsApp as fallback for email failure');
                    $whatsappSent = $this->sendWhatsAppPasswordReset($user->phone, $resetCode);
                    if ($whatsappSent) {
                        $resetSentVia = 'whatsapp';
                        Log::info('Password reset sent via WhatsApp (email fallback)');
                    }
                }
            }
        }

        if ($resetSentVia === 'failed') {
            return response()->json([
                'error' => 'Failed to send password reset code. Please try again later.'
            ], 500);
        }

        Log::info('Password reset code sent', [
            'user_id' => $user->id,
            'method' => $resetSentVia,
            'requested_method' => $preferredMethod,
            'login_type' => $isEmailLogin ? 'email' : 'phone'
        ]);

        return response()->json([
            'message' => 'Password reset code has been sent',
            'reset_sent_via' => $resetSentVia,
            'user_id' => $user->id,
            'email' => $user->email ?? null,
            'phone' => $user->phone ?? null
        ]);
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
                'text' => "🔑 إعادة تعيين كلمة المرور - DabApp\n\nرمز التحقق الخاص بك:\n\n*{$resetCode}*\n\n⏳ صالح لمدة 15 دقيقة فقط.\n🔒 لا تشارك هذا الرمز مع أحد.\n\nإذا لم تطلب هذا، تجاهل هذه الرسالة."
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
     *         description="Password reset successfully"
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'login' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Si c'est un email, on passe
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return;
                    }

                    // Si c'est un numéro de téléphone, il DOIT commencer par +
                    if (!str_starts_with($value, '+')) {
                        $fail('Phone number must include country code (e.g., +212...)');
                    }

                    // Vérifier le format valide
                    if (!preg_match('/^\+\d{10,15}$/', $value)) {
                        $fail('Invalid phone number format. Must be: +[country code][number]');
                    }
                }
            ],
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $login = $request->input('login');

        // ⭐ DÉTERMINER LE TYPE DE LOGIN DÈS LE DÉBUT
        $isEmailLogin = filter_var($login, FILTER_VALIDATE_EMAIL);

        Log::info('Password reset verification attempt', [
            'login' => $login,
            'is_email_login' => $isEmailLogin
        ]);

        // Find user by email or phone
        $user = $this->findUserByLogin($login);

        if (!$user) {
            Log::warning('Password reset verification failed - user not found', [
                'login' => $login
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        // Verify reset code
        $resetRecord = DB::table('password_resets')
            ->where('user_id', $user->id)
            ->where('code', $request->code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetRecord) {
            Log::warning('Invalid or expired reset code', [
                'user_id' => $user->id,
                'login' => $login
            ]);
            return response()->json(['error' => 'Invalid or expired reset code'], 401);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset code after successful use
        DB::table('password_resets')->where('id', $resetRecord->id)->delete();

        Log::info('Password reset code verified and deleted', [
            'user_id' => $user->id,
            'reset_record_id' => $resetRecord->id
        ]);

        // Also update Firebase password if needed
        try {
            $auth = (new Factory)
                ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
                ->createAuth();

            $auth->updateUser($user->email, [
                'password' => $request->password,
            ]);

            Log::info('Firebase password updated successfully', [
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update Firebase password', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        // Extract country & continent
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // ✅ GÉNÉRER LES DEUX TOKENS
        $tokens = $this->generateTokens($user, $country, $continent);

        $user->is_online = 1;
        $user->last_login = now();
        $user->save();

        Log::info('Password reset successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'login_type' => $isEmailLogin ? 'email' : 'phone',
            'country' => $country
        ]);

        return response()->json([
            'message' => 'Password has been reset successfully',
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
            'token' => $tokens['token'],
            'token_expiration' => $tokens['token_expiration'],
            'refresh_token' => $tokens['refresh_token'],
            'refresh_token_expiration' => $tokens['refresh_token_expiration'],
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
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully"
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

    /**
     * @OA\Get(
     *     path="/api/get-country",
     *     summary="Get user country and continent",
     *     tags={"Authentification"},
     *     @OA\Response(
     *         response=200,
     *         description="Location information retrieved"
     *     )
     * )
     */
    public function getCountry(Request $request)
    {
        // Extract country & continent from proxy headers
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';
        $ip = $request->ip();

        Log::info('Country detection', [
            'country' => $country,
            'continent' => $continent,
            'ip' => $ip
        ]);

        return response()->json([
            'country' => $country,
            'continent' => $continent,
            'ip' => $ip
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/countries",
     *     summary="Get all countries list with flags and dial codes",
     *     tags={"Authentification"},
     *     @OA\Response(
     *         response=200,
     *         description="List of all countries retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Countries retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Morocco"),
     *                     @OA\Property(property="dial_code", type="string", example="+212"),
     *                     @OA\Property(property="code", type="string", example="MA"),
     *                     @OA\Property(property="flag", type="string", example="https://flagcdn.com/w320/ma.png"),
     *                     @OA\Property(property="default", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=249),
     *             @OA\Property(property="detected_country", type="string", example="Morocco")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Failed to load countries")
     *         )
     *     )
     * )
     */
    public function getAllCountries()
    {
        try {
            $path = storage_path('app/countries.json');

            if (!file_exists($path)) {
                Log::error('Countries JSON file not found', ['path' => $path]);
                return response()->json([
                    'success' => false,
                    'error' => 'Countries file not found'
                ], 404);
            }

            $countries = json_decode(file_get_contents($path), true);

            if (!$countries) {
                Log::error('Failed to parse countries JSON');
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to load countries'
                ], 500);
            }

            // ✅ Detect user's country from proxy headers
            $detectedCountryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Morocco';

            // ✅ Find the detected country and mark it as default
            $detectedCountry = null;
            $otherCountries = [];

            foreach ($countries as $country) {
                if (strcasecmp($country['name'], $detectedCountryName) === 0) {
                    $country['default'] = true;
                    $detectedCountry = $country;
                } else {
                    $country['default'] = false;
                    $otherCountries[] = $country;
                }
            }

            // ✅ Put detected country first in the list
            if ($detectedCountry) {
                $sortedCountries = array_merge([$detectedCountry], $otherCountries);
            } else {
                // If detected country not found, just mark all as non-default
                $sortedCountries = $otherCountries;
                Log::warning('Detected country not found in list', [
                    'detected_country' => $detectedCountryName
                ]);
            }

            Log::info('Countries list retrieved', [
                'count' => count($sortedCountries),
                'detected_country' => $detectedCountryName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Countries retrieved successfully',
                'data' => $sortedCountries,
                'total' => count($sortedCountries),
                'detected_country' => $detectedCountryName
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving countries', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve countries',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/test-email",
     *     summary="Test email sending (development only)",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", example="test@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test email sent successfully"
     *     )
     * )
     */
    public function testEmail(Request $request)
    {
        // Only for development
        if (!app()->environment('local', 'staging')) {
            return response()->json(['error' => 'Not available in production'], 403);
        }

        $request->validate([
            'email' => 'required|email'
        ]);

        try {
            $resetCode = rand(1000, 9999);

            Log::info('Testing password reset email', [
                'email' => $request->email,
                'reset_code' => $resetCode
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json(['error' => 'User not found for test'], 404);
            }

            $user->notify(new PasswordResetNotification($resetCode));

            return response()->json([
                'message' => 'Test email sent successfully',
                'email' => $request->email,
                'reset_code' => $resetCode
            ]);
        } catch (\Exception $e) {
            Log::error('Test email failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/user/delete-request",
     *     summary="Request account deletion (Step 1)",
     *     description="Initiates account deletion process. Requires password. Sends OTP via Email and WhatsApp.",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", format="password", example="secret123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="OTP has been sent", @OA\JsonContent(@OA\Property(property="message", type="string", example="OTP has been sent to your email and WhatsApp / تم إرسال رمز التحقق إلى بريدك الإلكتروني و واتساب"))),
     *     @OA\Response(response=401, description="Unauthorized or invalid password", @OA\JsonContent(@OA\Property(property="error", type="string", example="Invalid password")))
     * )
     */
    public function deleteAccountRequest(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'password' => 'required|string'
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid password'], 401);
        }

        $otp = rand(1000, 9999);

        DB::table('otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'updated_at' => now()
            ]
        );

        $this->sendDeletionOtp($user, $otp);

        return response()->json(['message' => 'OTP has been sent to your email and WhatsApp / تم إرسال رمز التحقق إلى بريدك الإلكتروني و واتساب']);
    }

    /**
     * @OA\Post(
     *     path="/api/user/delete-confirm",
     *     summary="Confirm account deletion (Step 2)",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"otp"},
     *             @OA\Property(property="otp", type="string", example="1234")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Account deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid or expired OTP")
     *         )
     *     )
     * )
     */
    public function confirmDeleteAccount(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'otp' => 'required|string|size:4'
        ]);

        $otpRecord = DB::table('otps')
            ->where('user_id', $user->id)
            ->where('code', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['error' => 'Invalid OTP'], 400);
        }

        DB::table('otps')->where('id', $otpRecord->id)->delete();

        // Send confirmation email about 30-day reactivation
        // Sent before deletion to ensure delivery if notifications are queued
        Log::info('Attempting to send account deletion confirmation email', [
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name
        ]);

        try {
            $user->notify(new AccountDeletionStatusNotification());
            Log::info('Account deletion confirmation email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send deletion confirmation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send deletion confirmation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Send WhatsApp confirmation
        if (!empty($user->phone)) {
            $this->sendDeletionConfirmedWhatsApp($user->phone, $user->first_name);
        }

        // Perform Soft Delete
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully. You have 30 days to reactivate your account by logging in. / تم حذف الحساب بنجاح. لديك 30 يوماً لإعادة تفعيل حسابك عن طريق تسجيل الدخول.']);
    }

    /**
     * @OA\Post(
     *     path="/api/user/delete-resend-otp",
     *     summary="Resend account deletion OTP",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP has been resent / تم إعادة إرسال رمز التحقق")
     *         )
     *     )
     * )
     */
    public function resendDeletionOtp(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $otp = rand(1000, 9999);

        DB::table('otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(5),
                'updated_at' => now()
            ]
        );

        $this->sendDeletionOtp($user, $otp);

        return response()->json(['message' => 'OTP has been resent / تم إعادة إرسال رمز التحقق']);
    }

    private function sendDeletionOtp($user, $otp)
    {
        // User request: "pour delete account ne envoi pas a les deux whatsapp et mail"
        // Interpretation: Send via WhatsApp if available. If not, fallback to Email. Match "first whatsapp" preference.

        if (!empty($user->phone)) {
            // 1. Send via WhatsApp ONLY (if phone exists)
            $sent = $this->sendDeletionWhatsApp($user->phone, $otp);
            if (!$sent) {
                // Fallback to email if WhatsApp failed
                try {
                    $user->notify(new DeletionOtpNotification($otp));
                } catch (\Exception $e) {
                    Log::error('Failed to send deletion email fallback', ['error' => $e->getMessage()]);
                }
            }
        } else {
            // 2. Send via Email (if no phone)
            try {
                $user->notify(new DeletionOtpNotification($otp));
            } catch (\Exception $e) {
                Log::error('Failed to send deletion email', ['error' => $e->getMessage()]);
            }
        }
    }

    private function sendDeletionWhatsApp($phone, $otp)
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phone);

            $text = "⚠️ تأكيد حذف الحساب - DabApp\n\nرمز التحقق لحذف حسابك:\n\n*{$otp}*\n\n⏳ صالح لمدة 5 دقائق فقط.\n🔒 إذا لم تطلب هذا، تجاهل هذه الرسالة.";

            $payload = [
                'phonenumber' => '+' . $phoneNumber,
                'text' => $text
            ];

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer {$this->whatsappApiToken}",
                'Content-Type' => 'application/json',
            ])->post($this->whatsappApiUrl, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp deletion OTP send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendDeletionConfirmedWhatsApp($phone, $name)
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phone);

            $text = "🗑️ تم حذف حسابك - DabApp\n\nمرحباً {$name}،\nتم حذف حسابك بنجاح.\nلديك 30 يوماً لإعادة تفعيله عن طريق تسجيل الدخول.";

            $payload = [
                'phonenumber' => '+' . $phoneNumber,
                'text' => $text
            ];

            Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer {$this->whatsappApiToken}",
                'Content-Type' => 'application/json',
            ])->post($this->whatsappApiUrl, $payload);

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp deletion confirmation send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendReactivationWhatsApp($phone, $name)
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phone);

            $text = "🎉 تم إعادة تفعيل حسابك - DabApp\n\nمرحباً {$name}،\nتم إعادة تفعيل حسابك بنجاح.\nأهلاً بعودتك! 🚀";

            $payload = [
                'phonenumber' => '+' . $phoneNumber,
                'text' => $text
            ];

            Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer {$this->whatsappApiToken}",
                'Content-Type' => 'application/json',
            ])->post($this->whatsappApiUrl, $payload);

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp reactivation send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendActivationWhatsApp($phone, $name)
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phone);

            $text = "✅ تم تفعيل حسابك - DabApp\n\nمرحباً {$name}،\nتم التحقق من حسابك وتفعيله بنجاح.\nيمكنك الآن الاستمتاع بجميع الميزات. 🎉";

            $payload = [
                'phonenumber' => '+' . $phoneNumber,
                'text' => $text
            ];

            Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer {$this->whatsappApiToken}",
                'Content-Type' => 'application/json',
            ])->post($this->whatsappApiUrl, $payload);

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp activation send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }


    /**
     * @OA\Put(
     *     path="/api/user/language",
     *     summary="Change user language",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"language"},
     *             @OA\Property(
     *                 property="language",
     *                 type="string",
     *                 enum={"en", "ar"},
     *                 example="ar",
     *                 description="Language code (en=English, ar=Arabic)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Language updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Language updated successfully"),
     *             @OA\Property(property="language", type="string", example="ar")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function changeLanguage(Request $request)
    {
        $request->validate([
            'language' => 'required|in:en,ar'
        ]);

        $user = $request->user();  // ← Utilise $request->user()
        $user->language = $request->language;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Language updated successfully',
            'language' => $user->language
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/notify-unregistered",
     *     summary="Send WhatsApp notification to all unregistered users",
     *     description="Sends a WhatsApp message to all users who have not completed their registration (is_registration_completed = 0). Pass exclude_ids to skip users who already received the message.",
     *     tags={"Admin - Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="exclude_ids",
     *                 type="array",
     *                 description="User IDs to skip (already received the message)",
     *                 @OA\Items(type="integer", example=12)
     *             ),
     *             @OA\Property(
     *                 property="include_ids",
     *                 type="array",
     *                 description="Send ONLY to these user IDs (overrides exclude_ids)",
     *                 @OA\Items(type="integer", example=2441)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification report",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer", example=45, description="Total users targeted"),
     *             @OA\Property(property="sent", type="integer", example=42, description="Messages sent successfully"),
     *             @OA\Property(property="failed", type="integer", example=3, description="Messages that failed"),
     *             @OA\Property(
     *                 property="failed_users",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="user_id", type="integer", example=5),
     *                     @OA\Property(property="phone", type="string", example="+212666123456")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized - Admin token required")
     * )
     */
    public function notifyUnregisteredUsers(Request $request)
    {
        $includeIds = $request->input('include_ids', []);
        $excludeIds = $request->input('exclude_ids', []);

        $users = User::whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when(!empty($includeIds),
                fn($q) => $q->whereIn('id', $includeIds),
                fn($q) => $q->where('is_registration_completed', false)
                             ->when(!empty($excludeIds), fn($q2) => $q2->whereNotIn('id', $excludeIds))
            )
            ->get();

        $message = "تم حل المشكلة التقنية في رمز التحقق (OTP) 🎉😅\nيمكنك الآن طلب رمز جديد وتسجيل الدخول بسهولة 🚀";

        $sent = 0;
        $failed = 0;
        $failedUsers = [];

        foreach ($users as $user) {
            try {
                $phoneNumber = $this->formatPhoneNumber($user->phone);

                $payload = [
                    'phonenumber' => '+' . $phoneNumber,
                    'text' => $message,
                ];

                $response = Http::timeout(10)->withHeaders([
                    'Authorization' => "Bearer {$this->whatsappApiToken}",
                    'Content-Type' => 'application/json',
                ])->post($this->whatsappApiUrl, $payload);

                if ($response->successful()) {
                    $sent++;
                    Log::info('WhatsApp notification sent to unregistered user', [
                        'user_id' => $user->id,
                        'phone' => $user->phone,
                    ]);
                } else {
                    $failed++;
                    $failedUsers[] = ['user_id' => $user->id, 'phone' => $user->phone];
                    Log::warning('WhatsApp notification failed for unregistered user', [
                        'user_id' => $user->id,
                        'phone' => $user->phone,
                        'response' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                $failed++;
                $failedUsers[] = ['user_id' => $user->id, 'phone' => $user->phone];
                Log::error('Exception sending WhatsApp to unregistered user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'total' => $users->count(),
            'sent' => $sent,
            'failed' => $failed,
            'failed_users' => $failedUsers,
        ]);
    }
}
