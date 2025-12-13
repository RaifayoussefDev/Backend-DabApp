<?php

namespace App\Http\Controllers;

use App\Helpers\CountryHelper;
use App\Models\Authentication;
use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\SendOtpNotification;
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

class AuthAdminController extends Controller
{
    private $whatsappApiUrl = 'https://api.360messenger.com/v2/sendMessage';
    private $whatsappApiToken = 'fFUXfEtxJDqxX2lnteWxheeazIYyDriNBDn';

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

        Authentication::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $accessToken,
                'token_expiration' => $accessTokenExpiration,
                'refresh_token' => $refreshToken,
                'refresh_token_expiration' => $refreshTokenExpiration,
                'is_online' => true,
                'connection_date' => now(),
            ]
        );

        return [
            'token' => $accessToken,
            'token_expiration' => $accessTokenExpiration,
            'refresh_token' => $refreshToken,
            'refresh_token_expiration' => $refreshTokenExpiration,
        ];
    }



    /**
     * @OA\Post(
     *     path="/api/admin/login",
     *     summary="User login",
     *     tags={"Authentification - admin"},
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
            $user = User::where('email', $login)->first();
            Log::info('Login attempt with email', ['email' => $login]);
        } else {
            // Login is potentially a phone number - try direct match or with/without + prefix

            // First, try exact match
            $user = User::where('phone', $login)->first();

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

            Log::info('Login attempt with phone', [
                'phone' => $login,
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

        // ✅ VÉRIFIER SI L'UTILISATEUR EST VÉRIFIÉ
        if (!$user->verified) {
            Log::warning('Login attempt by unverified user', [
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

            Log::info('OTP sent for unverified user', [
                'user_id' => $user->id,
                'otp_sent_via' => $otpSentVia,
                'login_type' => $isEmailLogin ? 'email' : 'phone'
            ]);

            return response()->json([
                'error' => 'Account not verified',
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
        if ($isEmailLogin) {
            // L'utilisateur s'est connecté avec email → Envoyer OTP par email
            try {
                if (empty($user->email)) {
                    throw new \Exception('User has no email address');
                }

                $user->notify(new SendOtpNotification($otp));

                Log::info('OTP sent via Email (user logged in with email)', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return 'email';
            } catch (\Exception $e) {
                Log::error('Failed to send OTP via email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                // Fallback vers WhatsApp si email échoue
                if (!empty($user->phone)) {
                    $whatsappSent = $this->sendWhatsAppOtp($user->phone, $otp);
                    if ($whatsappSent) {
                        Log::info('OTP sent via WhatsApp (email fallback)', [
                            'user_id' => $user->id
                        ]);
                        return 'whatsapp';
                    }
                }

                return 'failed';
            }
        } else {
            // L'utilisateur s'est connecté avec téléphone → Envoyer OTP par WhatsApp
            if (!empty($user->phone)) {
                $whatsappSent = $this->sendWhatsAppOtp($user->phone, $otp);

                if ($whatsappSent) {
                    Log::info('OTP sent via WhatsApp (user logged in with phone)', [
                        'user_id' => $user->id,
                        'phone' => $user->phone
                    ]);
                    return 'whatsapp';
                }
            }

            // Fallback vers email si WhatsApp échoue
            try {
                if (empty($user->email)) {
                    throw new \Exception('User has no email address');
                }

                $user->notify(new SendOtpNotification($otp));

                Log::info('OTP sent via Email (WhatsApp fallback)', [
                    'user_id' => $user->id,
                    'email' => $user->email
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
                'text' => "🔐 رمز التحقق الخاص بك من dabapp.co هو: {$otp}\n\n⏳ هذا الرمز صالح لمدة 5 دقائق.\n❌ لا تشاركه مع أي شخص."
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
            // Login is a phone number - try with/without + prefix ONLY

            // First, try exact match
            $user = User::where('phone', $login)->first();

            if (!$user) {
                // Try removing/adding + prefix (for database compatibility)
                if (str_starts_with($login, '+')) {
                    $phoneWithoutPlus = substr($login, 1);
                    $user = User::where('phone', $phoneWithoutPlus)->first();
                } else {
                    $phoneWithPlus = '+' . $login;
                    $user = User::where('phone', $phoneWithPlus)->first();
                }
            }

            return $user;
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/resend-otp",
     *     summary="Resend OTP code",
     *     tags={"Authentification - admin"},
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
     *     path="/api/admin/resend-otp-email",
     *     summary="Resend OTP via email only",
     *     tags={"Authentification - admin"},
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

        // Use the same logic as login - force email only by temporarily removing phone
        $originalPhone = $user->phone;
        $user->phone = null; // Temporarily remove phone to force email

        $otpSentVia = $this->sendOtpWithWhatsAppFirst($user, $otp);

        $user->phone = $originalPhone; // Restore original phone

        if ($otpSentVia === 'failed') {
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
     *     path="/api/admin/verify-otp",
     *     summary="Verify OTP code",
     *     tags={"Authentification - admin"},
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

        // ✅ ACTIVER ET VÉRIFIER L'UTILISATEUR
        if (!$user->verified || !$user->is_active) {
            $user->verified = true;
            $user->is_active = true;
            $user->save();

            Log::info('User verified and activated', [
                'user_id' => $user->id,
                'was_verified' => $user->wasChanged('verified'),
                'was_activated' => $user->wasChanged('is_active')
            ]);
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
     *     path="/api/admin/me",
     *     summary="Get authenticated user information",
     *     tags={"Authentification - admin"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information retrieved successfully"
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $userWithData = User::with([
            'role',
            'role.permissions'
        ])->find($user->id);

        return response()->json([
            'user' => $userWithData
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/admin/logout",
     *     summary="User logout",
     *     tags={"Authentification - admin"},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out"
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
     *     path="/api/admin/refresh",
     *     summary="Refresh JWT access token using refresh token",
     *     tags={"Authentification - admin"},
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
     * @OA\Put(
     *     path="/api/admin/user/update",
     *     summary="Update user profile",
     *     tags={"Authentification - admin"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully"
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
     *     path="/api/admin/user/two-factor-toggle",
     *     summary="Toggle two-factor authentication",
     *     tags={"Authentification - admin"},
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
     *     path="/api/admin/forgot-password",
     *     summary="Send password reset OTP",
     *     tags={"Authentification - admin"},
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
                'text' => "🔐 رمز إعادة تعيين كلمة المرور الخاص بك من dabapp.co هو: {$resetCode}\n\n⏳ هذا الرمز صالح لمدة 15 دقيقة.\n❌ لا تشاركه مع أي شخص.\n\nإذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة."
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
     *     path="/api/admin/reset-password",
     *     summary="Reset password using OTP code",
     *     tags={"Authentification - admin"},
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
     *     path="/api/admin/change-password",
     *     summary="Change current password",
     *     tags={"Authentification - admin"},
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
}
