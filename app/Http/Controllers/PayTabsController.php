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
                    'response_code' => $verifiedCode,
                    'payment_result' => $verifiedMessage,
                    'completed_at' => now()
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
                    'response_code' => $verifiedCode,
                    'payment_result' => $verifiedMessage,
                    'failed_at' => now()
                ]);

                Log::info("Payment failed for listing #{$payment->listing_id}");

                return response()->json([
                    'success' => false,
                    'message' => 'Paiement échoué'
                ]);
            } else {
                // Statut en attente
                $payment->update([
                    'payment_status' => 'pending',
                    'response_code' => $verifiedCode,
                    'payment_result' => $verifiedMessage ?: 'En attente'
                ]);

                return response()->json([
                    'success' => null,
                    'message' => 'Paiement en cours de traitement'
                ]);
            }
        } else {
            Log::error('Could not verify payment with PayTabs', ['tran_ref' => $tranRef]);

            // Fallback: utiliser les données du callback directement
            if ($responseStatus === 'A' || strtolower($paymentResult) === 'completed') {
                $payment->update([
                    'payment_status' => 'completed',
                    'response_code' => $responseCode,
                    'payment_result' => $paymentResult,
                    'completed_at' => now()
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
                    'response_code' => $responseCode,
                    'payment_result' => $paymentResult,
                    'failed_at' => now()
                ]);

                return response()->json(['success' => false, 'message' => 'Paiement échoué']);
            }
        }
    }

    /**
     * API de retour après paiement
     */
    /**
     * API de retour après paiement - CORRIGÉE
     */
    public function return(Request $request)
    {
        Log::info('PayTabs Return received', [
            'method' => $request->getMethod(),
            'all_data' => $request->all(),
            'query_params' => $request->query(),
            'post_data' => $request->post(),
            'headers' => $request->headers->all()
        ]);

        $tranRef = $request->input('tran_ref') ?? $request->query('tran_ref');
        $cartId = $request->input('cart_id') ?? $request->query('cart_id');

        if (!$tranRef) {
            $tranRef = $request->input('tranRef') ?? $request->query('tranRef');
        }
        if (!$cartId) {
            $cartId = $request->input('cartId') ?? $request->query('cartId');
        }

        if (!$tranRef && !$cartId) {
            Log::error('PayTabs Return: No transaction reference found', $request->all());

            $errorUrl = 'https://dabapp.co/payment/error?reason=missing_ref';

            // TOUJOURS rediriger si c'est une requête de navigateur
            if (!$request->ajax() && !$request->wantsJson()) {
                return redirect()->away($errorUrl);
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

            $errorUrl = 'https://dabapp.co/payment/error?reason=payment_not_found&tran_ref=' . $tranRef;

            // TOUJOURS rediriger si c'est une requête de navigateur
            if (!$request->ajax() && !$request->wantsJson()) {
                return redirect()->away($errorUrl);
            }

            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé',
                'redirect_url' => $errorUrl
            ], 404);
        }

        // Attendre un peu pour que le callback soit traité en premier
        sleep(3);

        // Vérifier le statut du paiement si nécessaire
        if (in_array($payment->payment_status, ['pending', 'initiated'])) {
            Log::info("Verifying payment status for tran_ref: {$tranRef}");

            $verificationResult = $this->verifyPayment($tranRef);

            Log::info('PayTabs verification result', ['result' => $verificationResult]);

            if ($verificationResult) {
                $paymentResult = $verificationResult['payment_result'] ?? [];
                $responseStatus = $paymentResult['response_status'] ?? '';

                if ($responseStatus === 'A') {
                    $payment->update([
                        'payment_status' => 'completed',
                        'response_code' => $paymentResult['response_code'] ?? null,
                        'payment_result' => $paymentResult['response_message'] ?? 'Success',
                        'completed_at' => now()
                    ]);

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
                        'response_code' => $paymentResult['response_code'] ?? null,
                        'payment_result' => $paymentResult['response_message'] ?? 'Failed',
                        'failed_at' => now()
                    ]);

                    Log::info("Payment #{$payment->id} marked as failed");
                }
            } else {
                Log::warning("Could not verify payment status from PayTabs for tran_ref: {$tranRef}");
            }
        }

        $payment->refresh();

        // Construire les URLs vers ton front-end dabapp.co
        if ($payment->payment_status === 'completed') {
            $successUrl = 'https://dabapp.co/payment/success?' . http_build_query([
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency ?? 'SAR',
                'tran_ref' => $payment->tran_ref,
                'listing_id' => $payment->listing_id,
                'status' => 'completed'
            ]);

            Log::info("Payment successful, redirecting to: {$successUrl}");

            // FORCER LA REDIRECTION pour les requêtes de navigateur
            if (!$request->ajax() && !$request->wantsJson()) {
                Log::info("Forcing redirect to dabapp.co");
                return redirect()->away($successUrl);
            }

            return response()->json([
                'success' => true,
                'message' => 'Paiement réussi ! Votre annonce a été publiée.',
                'payment_id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'currency' => $payment->currency ?? 'SAR',
                'tran_ref' => $payment->tran_ref,
                'listing' => $payment->listing,
                'redirect_url' => $successUrl
            ]);
        } elseif ($payment->payment_status === 'failed') {
            $errorUrl = 'https://dabapp.co/payment/error?' . http_build_query([
                'payment_id' => $payment->id,
                'reason' => 'payment_failed',
                'tran_ref' => $payment->tran_ref,
                'error_code' => $payment->response_code,
                'error_message' => $payment->payment_result
            ]);

            Log::info("Payment failed, redirecting to: {$errorUrl}");

            // FORCER LA REDIRECTION pour les requêtes de navigateur
            if (!$request->ajax() && !$request->wantsJson()) {
                Log::info("Forcing redirect to dabapp.co error page");
                return redirect()->away($errorUrl);
            }

            return response()->json([
                'success' => false,
                'message' => 'Le paiement a échoué. Veuillez réessayer.',
                'payment_id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'currency' => $payment->currency ?? 'SAR',
                'tran_ref' => $payment->tran_ref,
                'error_code' => $payment->response_code,
                'error_message' => $payment->payment_result,
                'redirect_url' => $errorUrl
            ]);
        } else {
            // Status pending ou autre
            $pendingUrl = 'https://dabapp.co/payment/pending?' . http_build_query([
                'payment_id' => $payment->id,
                'tran_ref' => $payment->tran_ref,
                'status' => $payment->payment_status
            ]);

            Log::info("Payment pending, redirecting to: {$pendingUrl}");

            // FORCER LA REDIRECTION pour les requêtes de navigateur
            if (!$request->ajax() && !$request->wantsJson()) {
                Log::info("Forcing redirect to dabapp.co pending page");
                return redirect()->away($pendingUrl);
            }

            return response()->json([
                'success' => null,
                'message' => 'Votre paiement est en cours de traitement...',
                'payment_id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'currency' => $payment->currency ?? 'SAR',
                'tran_ref' => $payment->tran_ref,
                'redirect_url' => $pendingUrl
            ]);
        }
    }

    /**
     * Page de succès - MISE À JOUR pour supporter payment_id et tran_ref
     */
    public function paymentSuccess(Request $request)
    {
        $tranRef = $request->input('tran_ref');
        $paymentId = $request->input('payment_id');

        if (!$tranRef && !$paymentId) {
            return response()->json([
                'success' => false,
                'error' => 'payment_id ou tran_ref requis'
            ], 400);
        }

        // Rechercher le paiement par ID ou tran_ref
        $query = Payment::with(['listing', 'user']);

        if ($paymentId) {
            $query->where('id', $paymentId);
        } else {
            $query->where('tran_ref', $tranRef);
        }

        $payment = $query->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Paiement réussi ! Votre annonce a été publiée.',
            'payment_id' => $payment->id,
            'status' => $payment->payment_status,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'SAR',
            'tran_ref' => $payment->tran_ref,
            'listing' => $payment->listing,
            'redirect_url' => 'https://dabapp.co/payment/success?' . http_build_query([
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency ?? 'SAR',
                'tran_ref' => $payment->tran_ref,
                'listing_id' => $payment->listing_id,
                'status' => $payment->payment_status
            ])
        ]);
    }

    /**
     * Page d'erreur - NOUVELLE méthode
     */
    public function paymentError(Request $request)
    {
        $tranRef = $request->input('tran_ref');
        $paymentId = $request->input('payment_id');
        $reason = $request->input('reason', 'unknown');

        if (!$tranRef && !$paymentId) {
            return response()->json([
                'success' => false,
                'error' => 'payment_id ou tran_ref requis',
                'reason' => $reason
            ], 400);
        }

        // Rechercher le paiement si possible
        $payment = null;
        if ($paymentId) {
            $payment = Payment::with(['listing'])->find($paymentId);
        } elseif ($tranRef) {
            $payment = Payment::with(['listing'])->where('tran_ref', $tranRef)->first();
        }

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Le paiement a échoué. Veuillez réessayer.',
                'error' => 'Paiement non trouvé',
                'reason' => $reason,
                'tran_ref' => $tranRef,
                'payment_id' => $paymentId
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Le paiement a échoué. Veuillez réessayer.',
            'payment_id' => $payment->id,
            'status' => $payment->payment_status,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'SAR',
            'tran_ref' => $payment->tran_ref,
            'error_code' => $payment->response_code,
            'error_message' => $payment->payment_result,
            'reason' => $reason,
            'listing' => $payment->listing
        ]);
    }

    /**
     * Page d'attente - NOUVELLE méthode
     */
    public function paymentPending(Request $request)
    {
        $tranRef = $request->input('tran_ref');
        $paymentId = $request->input('payment_id');

        if (!$tranRef && !$paymentId) {
            return response()->json([
                'success' => false,
                'error' => 'payment_id ou tran_ref requis'
            ], 400);
        }

        // Rechercher le paiement
        $query = Payment::with(['listing']);

        if ($paymentId) {
            $query->where('id', $paymentId);
        } else {
            $query->where('tran_ref', $tranRef);
        }

        $payment = $query->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => null,
            'message' => 'Votre paiement est en cours de traitement...',
            'payment_id' => $payment->id,
            'status' => $payment->payment_status,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'SAR',
            'tran_ref' => $payment->tran_ref,
            'listing' => $payment->listing
        ]);
    }

    /**
     * Page d'annulation
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
     * Webhook pour les notifications de statut
     */
    public function webhook(Request $request)
    {
        Log::info('PayTabs Webhook received', $request->all());
        return $this->callback($request);
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
     * API pour vérifier le statut d'un paiement
     */
    public function checkPaymentStatus($paymentId)
    {
        return $this->getPaymentStatus($paymentId);
    }

    /**
     * Obtenir le statut d'un paiement
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
