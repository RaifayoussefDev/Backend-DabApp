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

            // Créer l'enregistrement de paiement
            $payment = Payment::create([
                'listing_id' => $listing->id,
                'user_id' => auth()->id() ?? null,
                'amount' => $request->amount,
                'currency' => $this->currency,
                'cart_id' => $cartId,
                'customer_name' => $request->name,
                'customer_email' => $request->email,
                'customer_phone' => $request->phone,
                'payment_status' => 'pending',
                'created_at' => now()
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
                    'payment_url' => $responseData['redirect_url'] ?? null
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
                    'payment_status' => 'failed',
                    'failed_at' => now()
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
     * Cette méthode est appelée automatiquement par PayTabs après le paiement
     */
    public function callback(Request $request)
    {
        Log::info('PayTabs Callback received', $request->all());

        $tranRef = $request->input('tran_ref');
        $cartId = $request->input('cart_id');
        $paymentResult = $request->input('payment_result');
        $responseCode = $request->input('response_code');

        // Trouver le paiement
        $payment = Payment::where('tran_ref', $tranRef)
                         ->orWhere('cart_id', $cartId)
                         ->first();

        if (!$payment) {
            Log::error('Payment not found for tran_ref: ' . $tranRef);
            return response()->json(['error' => 'Paiement non trouvé'], 404);
        }

        // Vérifier le statut du paiement auprès de PayTabs
        $verificationResult = $this->verifyPayment($tranRef);

        if ($verificationResult && $verificationResult['payment_result']['response_status'] === 'A') {
            // Paiement réussi
            $payment->update([
                'payment_status' => 'completed',
                'response_code' => $responseCode,
                'payment_result' => $paymentResult,
                'completed_at' => now()
            ]);

            // Publier automatiquement le listing
            $listing = $payment->listing;
            if ($listing) {
                $listing->update([
                    'status' => 'published',
                    'published_at' => now()
                ]);

                Log::info("Listing #{$listing->id} publié automatiquement après paiement réussi");
            }

            return response()->json([
                'success' => true,
                'message' => 'Paiement confirmé et listing publié'
            ]);

        } else {
            // Paiement échoué
            $payment->update([
                'payment_status' => 'failed',
                'response_code' => $responseCode,
                'payment_result' => $paymentResult,
                'failed_at' => now()
            ]);

            Log::info("Paiement échoué pour le listing #{$payment->listing_id}");

            return response()->json([
                'success' => false,
                'message' => 'Paiement échoué'
            ]);
        }
    }

    /**
     * API de retour après paiement (utilisé pour rediriger l'utilisateur)
     */
    public function return(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('PayTabs Return received', [
            'method' => $request->getMethod(),
            'all_data' => $request->all(),
            'query_params' => $request->query(),
            'post_data' => $request->post()
        ]);

        // Get transaction reference from either POST or GET data
        $tranRef = $request->input('tran_ref') ?? $request->query('tran_ref');
        $cartId = $request->input('cart_id') ?? $request->query('cart_id');

        // Handle case where parameters might be in different format
        if (!$tranRef) {
            $tranRef = $request->input('tranRef') ?? $request->query('tranRef');
        }
        if (!$cartId) {
            $cartId = $request->input('cartId') ?? $request->query('cartId');
        }

        if (!$tranRef && !$cartId) {
            Log::error('PayTabs Return: No transaction reference found', $request->all());

            $errorUrl = 'https://dabapp.co/payment/error?reason=missing_ref';

            // For GET requests, redirect directly
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

            // For GET requests, redirect directly
            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($errorUrl);
            }

            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé',
                'redirect_url' => $errorUrl
            ], 404);
        }

        // If this is a GET request, we might need to wait for the callback to process
        if ($request->isMethod('GET')) {
            // Wait a bit for the callback to be processed
            sleep(2);

            // Verify payment status with PayTabs if not already processed
            if ($payment->payment_status === 'pending') {
                $verificationResult = $this->verifyPayment($tranRef);

                if ($verificationResult && isset($verificationResult['payment_result']['response_status']) && $verificationResult['payment_result']['response_status'] === 'A') {
                    // Update payment status
                    $payment->update([
                        'payment_status' => 'completed',
                        'response_code' => $verificationResult['payment_result']['response_code'] ?? null,
                        'payment_result' => $verificationResult['payment_result']['response_message'] ?? 'Success',
                        'completed_at' => now()
                    ]);

                    // Publish the listing
                    $listing = $payment->listing;
                    if ($listing && $listing->status !== 'published') {
                        $listing->update([
                            'status' => 'published',
                            'published_at' => now()
                        ]);
                        Log::info("Listing #{$listing->id} published after successful payment verification");
                    }
                }
            }
        }

        // Reload the payment to get the latest status
        $payment->refresh();

        $responseData = [
            'payment_id' => $payment->id,
            'status' => $payment->payment_status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'tran_ref' => $payment->tran_ref
        ];

        if ($payment->payment_status === 'completed') {
            $responseData['success'] = true;
            $responseData['message'] = 'Paiement réussi ! Votre annonce a été publiée.';
            $responseData['listing'] = $payment->listing;
            $responseData['redirect_url'] = 'https://dabapp.co/payment/success?payment_id=' . $payment->id;

            // For GET requests, redirect directly
            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($responseData['redirect_url']);
            }

        } elseif ($payment->payment_status === 'failed') {
            $responseData['success'] = false;
            $responseData['message'] = 'Le paiement a échoué. Veuillez réessayer.';
            $responseData['redirect_url'] = 'https://dabapp.co/payment/error?payment_id=' . $payment->id;

            // For GET requests, redirect directly
            if ($request->isMethod('GET') && !$request->expectsJson()) {
                return redirect($responseData['redirect_url']);
            }

        } else {
            $responseData['success'] = null;
            $responseData['message'] = 'Votre paiement est en cours de traitement...';
            $responseData['redirect_url'] = 'https://dabapp.co/payment/pending?payment_id=' . $payment->id;

            // For GET requests, redirect directly
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
     * Webhook pour les notifications de statut (optionnel)
     */
    public function webhook(Request $request)
    {
        // Traiter les notifications push de PayTabs si configurées
        Log::info('PayTabs Webhook received', $request->all());

        // Même logique que le callback
        return $this->callback($request);
    }

    /**
     * API pour vérifier le statut d'un paiement (correspond à votre route existante)
     */
    public function checkPaymentStatus($paymentId)
    {
        return $this->getPaymentStatus($paymentId);
    }

    /**
     * API pour vérifier le statut d'un paiement
     */
    private function getPaymentStatus($id)
    {
        $payment = Payment::with('listing')->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé'
            ], 404);
        }

        $responseData = [
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'tran_ref' => $payment->tran_ref,
                'created_at' => $payment->created_at,
                'completed_at' => $payment->completed_at,
                'failed_at' => $payment->failed_at
            ]
        ];

        if ($payment->listing) {
            $responseData['listing'] = [
                'id' => $payment->listing->id,
                'title' => $payment->listing->title,
                'status' => $payment->listing->status,
                'published_at' => $payment->listing->published_at
            ];
        }

        return response()->json($responseData);
    }

    /**
     * Obtenir les détails d'un paiement
     */
    public function getPaymentDetails($paymentId)
    {
        $payment = Payment::with(['listing', 'user'])->find($paymentId);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé'
            ], 404);
        }

        // Vérifier que l'utilisateur peut accéder à ce paiement
        if (auth()->id() && $payment->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'tran_ref' => $payment->tran_ref,
                'cart_id' => $payment->cart_id,
                'created_at' => $payment->created_at,
                'completed_at' => $payment->completed_at,
                'failed_at' => $payment->failed_at,
                'listing' => $payment->listing ? [
                    'id' => $payment->listing->id,
                    'title' => $payment->listing->title,
                    'status' => $payment->listing->status,
                    'published_at' => $payment->listing->published_at
                ] : null,
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                    'email' => $payment->user->email
                ] : null
            ]
        ]);
    }

    /**
     * Obtenir l'historique des paiements d'un utilisateur
     */
    public function getPaymentHistory(Request $request)
    {
        $query = Payment::with(['listing'])
                       ->where('user_id', auth()->id())
                       ->orderBy('created_at', 'desc');

        // Filtres optionnels
        if ($request->has('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->has('listing_id')) {
            $query->where('listing_id', $request->listing_id);
        }

        $payments = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'payments' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total()
            ]
        ]);
    }

    /**
     * Page de succès (pour compatibilité)
     */
    public function paymentSuccess(Request $request)
    {
        $tranRef = $request->input('tran_ref');
        $payment = Payment::where('tran_ref', $tranRef)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Paiement réussi',
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'listing' => $payment->listing
            ]
        ]);
    }

    /**
     * Page d'annulation (pour compatibilité)
     */
    public function paymentCancel(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Paiement annulé par l\'utilisateur',
            'data' => $request->all()
        ]);
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
            // Test avec une requête basique
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
