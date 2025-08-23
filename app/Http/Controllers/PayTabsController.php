<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $this->currency  = config('paytabs.currency', 'AED');
        $this->region    = config('paytabs.region', 'ARE');

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
     * Créer un paiement (méthode générique)
     */
    public function createPayment(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|exists:listings,id',
            'amount' => 'required|numeric|min:0.1',
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string'
        ]);

        return $this->processPayment($request);
    }

    /**
     * Initier un paiement pour un listing spécifique
     */
    public function initiatePayment(Request $request, $listingId)
    {
        $request->merge(['listing_id' => $listingId]);

        $request->validate([
            'amount' => 'required|numeric|min:0.1',
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string'
        ]);

        return $this->processPayment($request);
    }

    /**
     * Logique commune pour traiter les paiements
     */
    private function processPayment(Request $request)
    {
        try {
            // Vérifier que le listing existe
            $listing = Listing::find($request->listing_id);
            if (!$listing) {
                return response()->json([
                    'success' => false,
                    'error' => 'Annonce non trouvée'
                ], 404);
            }

            // Générer un ID de commande unique
            $cartId = 'cart_' . time() . '_' . $listing->id;
            $cartDescription = "Paiement pour l'annonce: " . $listing->title;

            // Créer l'enregistrement de paiement avec les champs corrects
            $payment = Payment::create([
                'listing_id' => $listing->id,
                'user_id' => auth()->id() ?? null,
                'amount' => $request->amount,
                'cart_id' => $cartId,
                'payment_status' => 'pending',
                'verification_data' => [
                    'customer_name' => $request->name,
                    'customer_email' => $request->email,
                    'customer_phone' => $request->phone,
                    'cart_description' => $cartDescription,
                    'currency' => $this->currency
                ]
            ]);

            // URLs de callback
            $callbackUrl = config('app.url') . '/api/paytabs/callback';
            $returnUrl = config('app.url') . '/api/paytabs/return';

            // Préparer la requête PayTabs
            $paymentData = [
                "profile_id" => $this->profileId,
                "tran_type" => "sale",
                "tran_class" => "ecom",
                "cart_id" => $cartId,
                "cart_description" => $cartDescription,
                "cart_currency" => $this->currency,
                "cart_amount" => $request->amount,
                "callback" => $callbackUrl,
                "return" => $returnUrl,
                "customer_details" => [
                    "name" => $request->name,
                    "email" => $request->email,
                    "phone" => $request->phone,
                    "street1" => $request->input('address', 'N/A'),
                    "city" => $request->input('city', 'N/A'),
                    "state" => $request->input('state', 'N/A'),
                    "country" => $this->region,
                    "zip" => $request->input('zip', '00000')
                ]
            ];

            Log::info('Creating PayTabs payment', $paymentData);

            // Envoyer la requête à PayTabs
            $response = Http::withHeaders([
                "Authorization" => $this->serverKey,
                "Content-Type" => "application/json",
                "Accept" => "application/json"
            ])->post($this->baseUrl . 'payment/request', $paymentData);

            if ($response->successful()) {
                $responseData = $response->json();

                // Mettre à jour le paiement avec les données PayTabs
                $payment->update([
                    'tran_ref' => $responseData['tran_ref'] ?? null,
                    'verification_data' => array_merge($payment->verification_data ?? [], [
                        'payment_url' => $responseData['redirect_url'] ?? null,
                        'paytabs_response' => $responseData
                    ])
                ]);

                Log::info('PayTabs payment created successfully', [
                    'payment_id' => $payment->id,
                    'tran_ref' => $responseData['tran_ref'] ?? null
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement créé avec succès',
                    'payment_id' => $payment->id,
                    'payment_url' => $responseData['redirect_url'] ?? null,
                    'tran_ref' => $responseData['tran_ref'] ?? null
                ]);
            } else {
                Log::error('PayTabs payment creation failed', [
                    'response' => $response->json(),
                    'status' => $response->status()
                ]);

                $payment->update([
                    'payment_status' => 'failed'
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Erreur lors de la création du paiement',
                    'details' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error('PayTabs payment error: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur interne du serveur'
            ], 500);
        }
    }

    /**
     * Callback automatique de PayTabs (webhook)
     */
    public function callback(Request $request)
    {
        Log::info('PayTabs Callback received', $request->all());

        $tranRef = $request->input('tran_ref');
        $cartId = $request->input('cart_id');
        $paymentResult = $request->input('payment_result');
        $responseCode = $request->input('response_code');
        $responseStatus = $request->input('response_status');

        // Trouver le paiement
        $payment = Payment::where('tran_ref', $tranRef)
                         ->orWhere('cart_id', $cartId)
                         ->first();

        if (!$payment) {
            Log::error('Payment not found for tran_ref: ' . $tranRef . ', cart_id: ' . $cartId);
            return response()->json(['error' => 'Paiement non trouvé'], 404);
        }

        Log::info("Processing callback for payment #{$payment->id}", [
            'tran_ref' => $tranRef,
            'response_status' => $responseStatus,
            'payment_result' => $paymentResult
        ]);

        // Vérifier le statut du paiement auprès de PayTabs
        $verificationResult = $this->verifyPayment($tranRef);

        if ($verificationResult) {
            $verifiedStatus = $verificationResult['payment_result']['response_status'] ?? '';
            $verifiedMessage = $verificationResult['payment_result']['response_message'] ?? '';
            $verifiedCode = $verificationResult['payment_result']['response_code'] ?? '';

            Log::info('Payment verification result', [
                'payment_id' => $payment->id,
                'verified_status' => $verifiedStatus,
                'verified_message' => $verifiedMessage
            ]);

            if ($verifiedStatus === 'A') {
                // Paiement réussi (A = Approved)
                $payment->update([
                    'payment_status' => 'completed',
                    'resp_code' => $verifiedCode,
                    'resp_message' => $verifiedMessage,
                    'verification_data' => array_merge($payment->verification_data ?? [], [
                        'callback_data' => $request->all(),
                        'verification_result' => $verificationResult,
                        'completed_at' => now()
                    ])
                ]);

                // Publier automatiquement le listing
                $listing = $payment->listing;
                if ($listing && $listing->status !== 'published') {
                    $listing->update([
                        'status' => 'published',
                        'published_at' => now()
                    ]);

                    Log::info("Listing #{$listing->id} published automatically after successful payment");
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement confirmé et listing publié'
                ]);

            } elseif (in_array($verifiedStatus, ['D', 'F', 'E'])) {
                // Paiement échoué
                $payment->update([
                    'payment_status' => 'failed',
                    'resp_code' => $verifiedCode,
                    'resp_message' => $verifiedMessage,
                    'verification_data' => array_merge($payment->verification_data ?? [], [
                        'callback_data' => $request->all(),
                        'verification_result' => $verificationResult,
                        'failed_at' => now()
                    ])
                ]);

                Log::info("Payment failed for listing #{$payment->listing_id}");

                return response()->json([
                    'success' => false,
                    'message' => 'Paiement échoué'
                ]);
            }
        }

        // Fallback: use callback data directly
        if ($responseStatus === 'A' || strtolower($paymentResult) === 'completed') {
            $payment->update([
                'payment_status' => 'completed',
                'resp_code' => $responseCode,
                'resp_message' => $paymentResult,
                'verification_data' => array_merge($payment->verification_data ?? [], [
                    'callback_data' => $request->all(),
                    'completed_at' => now()
                ])
            ]);

            // Publier le listing
            $listing = $payment->listing;
            if ($listing && $listing->status !== 'published') {
                $listing->update([
                    'status' => 'published',
                    'published_at' => now()
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Paiement confirmé']);
        } else {
            $payment->update([
                'payment_status' => 'failed',
                'resp_code' => $responseCode,
                'resp_message' => $paymentResult,
                'verification_data' => array_merge($payment->verification_data ?? [], [
                    'callback_data' => $request->all(),
                    'failed_at' => now()
                ])
            ]);

            return response()->json(['success' => false, 'message' => 'Paiement échoué']);
        }
    }

    /**
     * API de retour après paiement
     */
    public function return(Request $request)
    {
        Log::info('PayTabs Return received', [
            'method' => $request->getMethod(),
            'all_data' => $request->all()
        ]);

        $tranRef = $request->input('tran_ref') ?? $request->query('tran_ref');
        $cartId = $request->input('cart_id') ?? $request->query('cart_id');

        if (!$tranRef && !$cartId) {
            Log::error('PayTabs Return: No transaction reference found', $request->all());
            $errorUrl = 'https://dabapp.co/payment/error?reason=missing_ref';

            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($errorUrl);
            }

            return response()->json([
                'success' => false,
                'error' => 'Référence de transaction manquante',
                'redirect_url' => $errorUrl
            ], 400);
        }

        $payment = Payment::where('tran_ref', $tranRef)
                         ->orWhere('cart_id', $cartId)
                         ->first();

        if (!$payment) {
            Log::error('PayTabs Return: Payment not found', [
                'tran_ref' => $tranRef,
                'cart_id' => $cartId
            ]);

            $errorUrl = 'https://dabapp.co/payment/error?reason=payment_not_found';

            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($errorUrl);
            }

            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé',
                'redirect_url' => $errorUrl
            ], 404);
        }

        // Always verify payment status with PayTabs
        sleep(3);

        if (in_array($payment->payment_status, ['pending', 'initiated'])) {
            Log::info("Verifying payment status for tran_ref: {$tranRef}");

            $verificationResult = $this->verifyPayment($tranRef);

            if ($verificationResult) {
                $paymentResult = $verificationResult['payment_result'] ?? [];
                $responseStatus = $paymentResult['response_status'] ?? '';

                if ($responseStatus === 'A') {
                    // Update payment status to completed
                    $payment->update([
                        'payment_status' => 'completed',
                        'resp_code' => $paymentResult['response_code'] ?? null,
                        'resp_message' => $paymentResult['response_message'] ?? 'Success',
                        'verification_data' => array_merge($payment->verification_data ?? [], [
                            'verification_result' => $verificationResult,
                            'completed_at' => now()
                        ])
                    ]);

                    // Publish the listing automatically
                    $listing = $payment->listing;
                    if ($listing && $listing->status !== 'published') {
                        $listing->update([
                            'status' => 'published',
                            'published_at' => now()
                        ]);
                        Log::info("Listing #{$listing->id} published automatically after successful payment");
                    }

                    Log::info("Payment #{$payment->id} marked as completed and listing published");

                } elseif (in_array($responseStatus, ['D', 'F', 'E'])) {
                    $payment->update([
                        'payment_status' => 'failed',
                        'resp_code' => $paymentResult['response_code'] ?? null,
                        'resp_message' => $paymentResult['response_message'] ?? 'Failed',
                        'verification_data' => array_merge($payment->verification_data ?? [], [
                            'verification_result' => $verificationResult,
                            'failed_at' => now()
                        ])
                    ]);

                    Log::info("Payment #{$payment->id} marked as failed");
                }
            }
        }

        // Reload the payment
        $payment->refresh();

        $currency = $payment->verification_data['currency'] ?? $this->currency;

        $responseData = [
            'payment_id' => $payment->id,
            'status' => $payment->payment_status,
            'amount' => $payment->amount,
            'currency' => $currency,
            'tran_ref' => $payment->tran_ref
        ];

        if ($payment->payment_status === 'completed') {
            $responseData['success'] = true;
            $responseData['message'] = 'Paiement réussi ! Votre annonce a été publiée.';
            $responseData['listing'] = $payment->listing;
            $responseData['redirect_url'] = 'https://dabapp.co/payment/success?payment_id=' . $payment->id;

            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($responseData['redirect_url']);
            }

        } elseif ($payment->payment_status === 'failed') {
            $responseData['success'] = false;
            $responseData['message'] = 'Le paiement a échoué. Veuillez réessayer.';
            $responseData['redirect_url'] = 'https://dabapp.co/payment/error?payment_id=' . $payment->id;

            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($responseData['redirect_url']);
            }

        } else {
            $responseData['success'] = null;
            $responseData['message'] = 'Votre paiement est en cours de traitement...';
            $responseData['redirect_url'] = 'https://dabapp.co/payment/pending?payment_id=' . $payment->id;

            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($responseData['redirect_url']);
            }
        }

        return response()->json($responseData);
    }

    /**
     * Vérifier le statut d'un paiement auprès de PayTabs
     */
    private function verifyPayment($tranRef)
    {
        try {
            $response = Http::withHeaders([
                "Authorization" => $this->serverKey,
                "Content-Type"  => "application/json",
                "Accept"        => "application/json"
            ])->post($this->baseUrl . 'payment/query', [
                "profile_id" => $this->profileId,
                "tran_ref" => $tranRef
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('PayTabs verification failed', $response->json());
            return null;

        } catch (\Exception $e) {
            Log::error('PayTabs verification error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Webhook pour les notifications de statut
     */
    public function webhook(Request $request)
    {
        Log::info('PayTabs Webhook received', $request->all());
        return $this->callback($request);
    }

    /**
     * API pour vérifier le statut d'un paiement
     */
    public function checkPaymentStatus($paymentId)
    {
        $payment = Payment::with('listing')->find($paymentId);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé'
            ], 404);
        }

        $currency = $payment->verification_data['currency'] ?? $this->currency;

        $responseData = [
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'currency' => $currency,
                'tran_ref' => $payment->tran_ref,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at
            ]
        ];

        if ($payment->listing) {
            $responseData['listing'] = [
                'id' => $payment->listing->id,
                'title' => $payment->listing->title,
                'status' => $payment->listing->status,
                'published_at' => $payment->listing->published_at ?? null
            ];
        }

        return response()->json($responseData);
    }

    /**
     * Force success redirect method
     */
    public function forceSuccessRedirect(Request $request)
    {
        $paymentId = $request->input('payment_id');
        $tranRef = $request->input('tran_ref');

        if ($paymentId) {
            $payment = Payment::find($paymentId);
        } elseif ($tranRef) {
            $payment = Payment::where('tran_ref', $tranRef)->first();
        } else {
            return response()->json(['error' => 'Payment ID or transaction reference required'], 400);
        }

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Verify payment with PayTabs
        if ($payment->tran_ref) {
            $verificationResult = $this->verifyPayment($payment->tran_ref);

            if ($verificationResult && $verificationResult['payment_result']['response_status'] === 'A') {
                // Update payment and listing
                $payment->update([
                    'payment_status' => 'completed',
                    'verification_data' => array_merge($payment->verification_data ?? [], [
                        'force_verified_at' => now(),
                        'verification_result' => $verificationResult
                    ])
                ]);

                if ($payment->listing && $payment->listing->status !== 'published') {
                    $payment->listing->update([
                        'status' => 'published',
                        'published_at' => now()
                    ]);
                }
            }
        }

        // Redirect based on current status
        if ($payment->payment_status === 'completed') {
            return redirect('https://dabapp.co/payment/success?payment_id=' . $payment->id);
        } else {
            return redirect('https://dabapp.co/payment/pending?payment_id=' . $payment->id);
        }
    }

    /**
     * Test de connexion PayTabs
     */
    public function testConnection()
    {
        if (!$this->baseUrl || !$this->profileId || !$this->serverKey) {
            return response()->json([
                'success' => false,
                'error' => 'Configuration PayTabs manquante'
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                "Authorization" => $this->serverKey,
                "Content-Type"  => "application/json",
                "Accept"        => "application/json"
            ])->post($this->baseUrl . 'payment/query', [
                "profile_id" => $this->profileId,
                "tran_ref" => "test_connection"
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connexion PayTabs réussie',
                'config' => [
                    'base_url' => $this->baseUrl,
                    'profile_id' => $this->profileId,
                    'currency' => $this->currency,
                    'region' => $this->region
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur de connexion PayTabs: ' . $e->getMessage()
            ], 500);
        }
    }
}