<?php
// app/Services/WhatsappOtpService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappOtpService
{
    protected $apiUrl = 'https://gateway.360messenger.com/api/v1/message'; // Change if needed
    protected $apiToken = 'your_api_token_here';

    public function sendOtp($phoneNumber, $otp)
    {
        $message = "Votre code OTP est : $otp";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
        ])->post($this->apiUrl, [
            'phone' => $phoneNumber, // Format: "2126XXXXXXXX"
            'message' => $message,
        ]);

        return $response->json();
    }
}
