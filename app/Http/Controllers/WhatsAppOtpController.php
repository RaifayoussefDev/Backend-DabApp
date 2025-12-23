<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppOtpController extends Controller
{
    private $apiUrl = 'https://api.360messenger.com/v2/sendMessage';
    private $apiToken = 'dYXcSz0yjWT1jP0vsAb6TNQ6p3epz4xZeYY';

    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $otp = rand(100000, 999999);
        session(['otp' => $otp]);

        // Format phone number - ensure it includes country code
        $phoneNumber = $this->formatPhoneNumber($request->phone);

        // 360messenger API correct format (tested with Postman)
        $payload = [
            'phonenumber' => '+' . $phoneNumber, // Add + prefix as required
            'text' => "Your OTP code is: {$otp}"
        ];

        Log::info('Sending WhatsApp OTP', [
            'phone' => $phoneNumber,
            'payload' => $payload
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl, $payload);

        // Log the response for debugging
        Log::info('WhatsApp API Response', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => $response->json(),
                'status_code' => $response->status()
            ], 422); // Return 422 instead of the original error status
        }
    }

    private function formatPhoneNumber($phone)
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If phone doesn't start with country code, add Morocco's (+212)
        if (!str_starts_with($phone, '212') && !str_starts_with($phone, '+212')) {
            // Remove leading zero if present
            if (str_starts_with($phone, '0')) {
                $phone = substr($phone, 1);
            }
            $phone = '212' . $phone;
        }

        return $phone;
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $sessionOtp = session('otp');

        if ($sessionOtp && $request->otp == $sessionOtp) {
            session()->forget('otp'); // Clear OTP after successful verification
            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid OTP code'
        ], 422);
    }
}
