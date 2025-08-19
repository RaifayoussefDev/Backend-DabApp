<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayTabsController extends Controller
{
    private $baseUrl;
    private $profileId;
    private $serverKey;
    private $currency;
    private $region;

    public function __construct()
    {
        $this->baseUrl   = config('paytabs.base_url', 'https://secure.paytabs.com/');
        $this->profileId = config('paytabs.profile_id');
        $this->serverKey = config('paytabs.server_key');
        $this->currency  = config('paytabs.currency', 'AED'); // 💡 depuis .env
        $this->region    = config('paytabs.region', 'ARE');   // 💡 depuis .env

        // Endpoint selon la région
        $endpoints = [
            'ARE' => 'https://secure.paytabs.com/',
            'SAU' => 'https://secure.paytabs.sa/',
            'OMN' => 'https://secure-oman.paytabs.com/',
            'JOR' => 'https://secure-jordan.paytabs.com/',
            'EGY' => 'https://secure-egypt.paytabs.com/',
            'GLOBAL' => 'https://secure-global.paytabs.com/',
        ];

        $this->baseUrl = $endpoints[$this->region] ?? $endpoints['ARE'];
    }

    /**
     * Créer un paiement (API)
     */
    public function createPayment(Request $request)
    {
        if (!$this->baseUrl || !$this->profileId || !$this->serverKey) {
            return response()->json([
                "success" => false,
                "error" => "PayTabs configuration is missing"
            ], 500);
        }

        // 💡 Préparer le payload
        $data = [
            "profile_id" => $this->profileId,
            "tran_type" => "sale",
            "tran_class" => "ecom",
            "cart_id" => uniqid("cart_"),
            "cart_currency" => $this->currency,
            "cart_amount" => $request->amount ?? 10,
            "cart_description" => "Test API Payment",
            "paypage_lang" => "en",
            "customer_details" => [
                "name" => $request->name ?? "Test User",
                "email" => $request->email ?? "test@example.com",
                "phone" => $request->phone ?? "00212600000000",
                "street1" => "Street Address",
                "city" => "Casablanca",
                "state" => "CS",
                "country" => "AE",
                "zip" => "20000"
            ],
            "callback" => url("/api/paytabs/callback"),
            "return" => url("/api/paytabs/return")
        ];

        // 💳 Ajouter les détails de la carte si fournis (pour paiement direct)
        if ($request->filled(['card_number', 'card_cvv', 'card_expiry_mm', 'card_expiry_yy'])) {
            $data["card_number"] = $request->card_number;
            $data["card_holder_name"] = $request->name ?? "Test User";
            $data["card_cvv"] = $request->card_cvv;
            $data["card_expiry_mm"] = $request->card_expiry_mm;
            $data["card_expiry_yy"] = $request->card_expiry_yy;
        }

        try {
            $response = Http::withHeaders([
                "Authorization" => $this->serverKey,
                "Content-Type"  => "application/json",
                "Accept"        => "application/json"
            ])->post($this->baseUrl . 'payment/request', $data);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "error" => $e->getMessage()
            ], 500);
        }
    }


    public function callback(Request $request)
    {
        $tranRef = $request->input('tran_ref');
        $payment = Payment::where('tran_ref', $tranRef)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Ici tu peux vérifier via API PayTabs si le paiement est confirmé
        // Pour simplifier, on suppose que la callback contient 'payment_result' = success
        if ($request->input('payment_result') === 'success') {
            $payment->update(['payment_status' => 'completed']);

            // Publier le listing
            $listing = $payment->listing;
            $listing->update(['status' => 'published']);

            return response()->json(['message' => 'Payment confirmed, listing published']);
        } else {
            $payment->update(['payment_status' => 'failed']);
            return response()->json(['message' => 'Payment failed']);
        }
    }


    public function return(Request $request)
    {
        return response()->json([
            "status" => "return_received",
            "data" => $request->all()
        ]);
    }
}
