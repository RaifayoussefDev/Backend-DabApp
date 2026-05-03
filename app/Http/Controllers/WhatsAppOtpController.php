<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppOtpController extends Controller
{
    private $aisensyApiUrl = 'https://backend.aisensy.com/campaign/t1/api/v2';

    /**
     * Send OTP via AiSensy WhatsApp
     * POST /api/test-aisensy
     * Body: { "phone": "+212688808238", "otp": "123456" }
     */
    public function testAisensy(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp'   => 'nullable|string',
        ]);

        $otp    = $request->input('otp', (string) rand(100000, 999999));
        $rawPhone = trim($request->phone);
        $hasPlus  = str_starts_with($rawPhone, '+');
        $phone    = ($hasPlus ? '+' : '') . preg_replace('/\D/', '', $rawPhone);

        $success = $this->sendAisensyOtp($phone, $otp);

        return response()->json([
            'success'  => $success,
            'otp_sent' => $otp,
            'phone'    => $phone,
        ], $success ? 200 : 422);
    }

    /**
     * Core method: send OTP via AiSensy — matches the working curl format exactly
     */
    public function sendAisensyOtp(string $phone, string $otp): bool
    {
        $payload = [
            'apiKey'              => env('AISENSY_API_KEY'),
            'campaignName'        => env('AISENSY_CAMPAIGN_NAME', 'DabApp'),
            'destination'         => $phone,
            'userName'            => env('AISENSY_USERNAME', 'Fadel Brothers Group L.L.C'),
            'templateParams'      => [$otp],
            'source'              => 'new-landing-page form',
            'media'               => (object)[],
            'buttons'             => [
                [
                    'type'       => 'button',
                    'sub_type'   => 'url',
                    'index'      => 0,
                    'parameters' => [
                        ['type' => 'text', 'text' => $otp],
                    ],
                ],
            ],
            'carouselCards'       => [],
            'location'            => (object)[],
            'attributes'          => (object)[],
            'paramsFallbackValue' => ['FirstName' => 'user'],
        ];

        Log::info('AiSensy OTP send', ['phone' => $phone]);

        $ch = curl_init($this->aisensyApiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            Log::error('AiSensy cURL error', ['error' => $curlErr]);
            return false;
        }

        $decoded = json_decode($raw, true);
        Log::info('AiSensy response', ['status' => $httpCode, 'body' => $decoded]);

        return $httpCode >= 200 && $httpCode < 300;
    }

    public function sendOtp(Request $request)
    {
        $request->validate(['phone' => 'required|string']);

        $otp      = (string) rand(100000, 999999);
        $rawPhone = trim($request->phone);
        $hasPlus  = str_starts_with($rawPhone, '+');
        $phone    = ($hasPlus ? '+' : '') . preg_replace('/\D/', '', $rawPhone);

        session(['otp' => $otp]);

        $success = $this->sendAisensyOtp($phone, $otp);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'OTP sent successfully' : 'Failed to send OTP',
        ], $success ? 200 : 422);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $sessionOtp = session('otp');

        if ($sessionOtp && $request->otp == $sessionOtp) {
            session()->forget('otp');
            return response()->json(['success' => true, 'message' => 'OTP verified successfully']);
        }

        return response()->json(['success' => false, 'message' => 'Invalid OTP code'], 422);
    }
}
