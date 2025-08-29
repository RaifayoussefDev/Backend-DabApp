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
    private $whatsappApiToken = 'pj0y5xb38khWfp0V0qppIxwKelv7tgTg5yx';

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
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users',
            'phone'      => 'required|string|unique:users',
            'password'   => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Get country from proxy headers
        $countryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Morocco';

        // Use helper to process country and phone
        $countryData = CountryHelper::processCountryAndPhoneByName($request->phone, $countryName);

        Log::info('Registration with country processing', [
            'original_phone' => $request->phone,
            'country_name' => $countryName,
            'formatted_phone' => $countryData['formatted_phone'],
            'country_code' => $countryData['country_code'],
            'country_id' => $countryData['country_id'],
        ]);

        // Create user with 2FA enabled by default
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'phone'      => $countryData['formatted_phone'],
            'password'   => Hash::make($request->password),
            'role_id'    => $request->role_id ?? 1,
            'verified'   => false,
            'is_active'  => true,
            'is_online'  => false,
            'language'   => 'fr',
            'timezone'   => 'Africa/Casablanca',
            'two_factor_enabled' => true,
            'country_id' => $countryData['country_id'],
        ]);

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

        // Send OTP with WhatsApp first, email fallback
        $otpSentVia = $this->sendOtpWithWhatsAppFirst($user, $otp);

        if ($otpSentVia === 'failed') {
            return response()->json([
                'error' => 'Registration successful but failed to send OTP. Please login to resend.',
                'user_id' => $user->id
            ], 500);
        }

        return response()->json([
            'message' => 'Registration successful, OTP required for verification',
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
            'requiresOTP' => true,
            'user_id' => $user->id,
            'country' => $countryData['country_name'],
            'country_code' => $countryData['country_code'],
            'country_id' => $countryData['country_id'],
            'formatted_phone' => $countryData['formatted_phone'],
            'otp_sent_via' => $otpSentVia
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
     *             @OA\Property(property="login", type="string", example="john.doe@example.com"),
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
            return response()->json(['error' => 'User is inactive'], 403);
        }

        $user->is_online = 1;
        $user->last_login = now();
        $user->save();

        // Extract country & continent from proxy headers
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // Create token with claims
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

            // Send OTP with WhatsApp first, email fallback
            $otpSentVia = $this->sendOtpWithWhatsAppFirst($user, $otp);

            if ($otpSentVia === 'failed') {
                return response()->json([
                    'error' => 'Unable to send OTP code. Please try again later.',
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
                'otp_sent_via' => $otpSentVia
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
     * @OA\Post(
     *     path="/api/resend-otp",
     *     summary="Resend OTP code",
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
     *         description="OTP resent successfully"
     *     )
     * )
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
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

        // Find user
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

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
     *             @OA\Property(property="login", type="string", example="john.doe@example.com")
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
            'login' => 'required|string'
        ]);

        // Rate limiting
        $cacheKey = 'otp_email_resend_' . md5($request->login);
        $lastRequest = Cache::get($cacheKey);

        if ($lastRequest && now()->diffInSeconds($lastRequest) < 60) {
            return response()->json([
                'error' => 'Please wait before requesting another OTP'
            ], 429);
        }

        // Find user
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (empty($user->email)) {
            return response()->json(['error' => 'User has no email address'], 400);
        }

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

        // Send OTP via email only
        try {
            $user->notify(new SendOtpNotification($otp));

            Log::info('OTP resent via email', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'message' => 'A new OTP has been sent to your email',
                'otp_sent_via' => 'email',
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send OTP via email in resendOtpEmail', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to send OTP via email. Please try again later.'
            ], 500);
        }
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
     *             @OA\Property(property="login", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="otp", type="string", example="1234")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP valid, authentication successful"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired OTP"
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'otp' => 'required|string',
        ]);

        // Find user
        $user = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $request->login)->first()
            : User::where('phone', $request->login)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Verify OTP
        $otpRecord = DB::table('otps')
            ->where('user_id', $user->id)
            ->where('code', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['error' => 'Invalid or expired OTP'], 401);
        }

        // Delete OTP after use
        DB::table('otps')->where('id', $otpRecord->id)->delete();

        // Mark user as verified if not already
        if (!$user->verified) {
            $user->verified = true;
            $user->save();
        }

        // Extract country & continent
        $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
        $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';

        // Create token with claims
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
     *     summary="Get authenticated user information",
     *     tags={"Authentification"},
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

        return response()->json([
            'user' => $userWithData
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="User logout",
     *     tags={"Authentification"},
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
     *     path="/api/refresh",
     *     summary="Refresh JWT token",
     *     tags={"Authentification"},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully"
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
     *     summary="Update user profile",
     *     tags={"Authentification"},
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
            'requested_method' => $preferredMethod
        ]);

        return response()->json([
            'message' => 'Password reset code has been sent',
            'reset_sent_via' => $resetSentVia,
            'user_id' => $user->id,
            'email' => $user->email ?? null
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
        }

        // Extract country & continent
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
}
