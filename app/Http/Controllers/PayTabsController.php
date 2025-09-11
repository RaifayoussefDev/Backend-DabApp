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

        if (!$tranRef && !$cartId) {
            Log::error('PayTabs Callback: Missing tran_ref and cart_id');
            return response()->json(['error' => 'Données manquantes'], 400);
        }

        // Trouver le paiement
        $payment = Payment::where(function ($query) use ($tranRef, $cartId) {
            if ($tranRef) $query->where('tran_ref', $tranRef);
            if ($cartId) $query->orWhere('cart_id', $cartId);
        })->with('listing')->first();

        if (!$payment) {
            Log::error('PayTabs Callback: Payment not found', [
                'tran_ref' => $tranRef,
                'cart_id' => $cartId
            ]);
            return response()->json(['error' => 'Paiement non trouvé'], 404);
        }

        Log::info("Processing callback for payment #{$payment->id}", [
            'current_payment_status' => $payment->payment_status,
            'current_listing_status' => $payment->listing->status ?? 'no_listing',
            'tran_ref' => $tranRef,
            'response_status' => $responseStatus,
            'payment_result' => $paymentResult
        ]);

        // PREMIÈRE ÉTAPE: Traiter les données du callback direct
        $callbackProcessed = $this->processCallbackData($payment, $request->all());

        // DEUXIÈME ÉTAPE: Vérifier avec l'API PayTabs si nécessaire
        if (!$callbackProcessed && $tranRef) {
            Log::info("Callback data not conclusive, verifying with PayTabs API");
            $this->verifyAndUpdatePaymentStatus($payment, $tranRef);
        }

        $payment->refresh();

        // LOG FINAL
        Log::info("Final callback processing result", [
            'payment_id' => $payment->id,
            'payment_status' => $payment->payment_status,
            'listing_status' => $payment->listing->status ?? 'no_listing',
            'listing_published_at' => $payment->listing->published_at ?? null
        ]);

        return response()->json([
            'success' => $payment->payment_status === 'completed',
            'message' => $this->getStatusMessage($payment->payment_status),
            'payment_id' => $payment->id,
            'payment_status' => $payment->payment_status,
            'listing_status' => $payment->listing->status ?? null,
            'listing_published' => $payment->listing && $payment->listing->status === 'published'
        ]);
    }

    private function processCallbackData($payment, $callbackData)
    {
        $responseStatus = $callbackData['response_status'] ?? '';
        $responseCode = $callbackData['response_code'] ?? '';
        $paymentResult = $callbackData['payment_result'] ?? '';
        $responseMessage = $callbackData['response_message'] ?? $paymentResult;

        Log::info("Processing callback data for payment #{$payment->id}", [
            'response_status' => $responseStatus,
            'response_code' => $responseCode,
            'payment_result' => $paymentResult,
            'response_message' => $responseMessage
        ]);

        // PAIEMENT RÉUSSI
        if (
            $responseStatus === 'A' ||
            (strtolower($paymentResult) === 'completed' && $responseStatus !== 'D' && $responseStatus !== 'F')
        ) {

            $payment->update([
                'payment_status' => 'completed',
                'response_code' => $responseCode,
                'payment_result' => $responseMessage ?: 'Payment completed',
                'completed_at' => now()
            ]);

            // PUBLIER LE LISTING AUTOMATIQUEMENT
            $this->autoPublishListing($payment);

            Log::info("✅ Payment #{$payment->id} completed via callback data");
            return true;
        } elseif (
            in_array($responseStatus, ['D', 'F', 'E']) ||
            in_array(strtolower($paymentResult), ['failed', 'declined', 'error'])
        ) {
            // PAIEMENT ÉCHOUÉ
            $payment->update([
                'payment_status' => 'failed',
                'response_code' => $responseCode,
                'payment_result' => $responseMessage ?: 'Payment failed',
                'failed_at' => now()
            ]);

            Log::info("❌ Payment #{$payment->id} failed via callback data");
            return true;
        } elseif ($responseStatus === 'P' || strtolower($paymentResult) === 'pending') {
            // PAIEMENT EN ATTENTE
            $payment->update([
                'payment_status' => 'pending',
                'response_code' => $responseCode,
                'payment_result' => $responseMessage ?: 'Payment pending'
            ]);

            Log::info("⏳ Payment #{$payment->id} pending via callback data");
            return true;
        }

        return false; // Données non concluantes
    }
    private function verifyAndUpdatePaymentStatus($payment, $tranRef)
    {
        $verificationResult = $this->verifyPayment($tranRef);

        if (!$verificationResult) {
            Log::error("PayTabs API verification failed for payment #{$payment->id}");
            return false;
        }

        $paymentResult = $verificationResult['payment_result'] ?? [];
        $responseStatus = $paymentResult['response_status'] ?? '';
        $responseMessage = $paymentResult['response_message'] ?? '';
        $responseCode = $paymentResult['response_code'] ?? '';

        Log::info("PayTabs API verification result for payment #{$payment->id}", [
            'response_status' => $responseStatus,
            'response_message' => $responseMessage
        ]);

        if ($responseStatus === 'A') {
            // PAIEMENT APPROUVÉ
            $payment->update([
                'payment_status' => 'completed',
                'response_code' => $responseCode,
                'payment_result' => $responseMessage ?: 'Payment approved',
                'completed_at' => now()
            ]);

            // PUBLIER LE LISTING
            $this->autoPublishListing($payment);

            Log::info("✅ Payment #{$payment->id} completed via API verification");
            return true;
        } elseif (in_array($responseStatus, ['D', 'F', 'E'])) {
            // PAIEMENT REJETÉ
            $payment->update([
                'payment_status' => 'failed',
                'response_code' => $responseCode,
                'payment_result' => $responseMessage ?: 'Payment declined',
                'failed_at' => now()
            ]);

            Log::info("❌ Payment #{$payment->id} failed via API verification");
            return true;
        }

        return false;
    }

    private function autoPublishListing($payment)
    {
        $listing = $payment->listing;

        if (!$listing) {
            Log::warning("Payment #{$payment->id} has no associated listing");
            return false;
        }

        // Vérifier que le paiement est vraiment completed
        if ($payment->payment_status !== 'completed') {
            Log::warning("Cannot publish listing #{$listing->id}: payment status is '{$payment->payment_status}', not 'completed'");
            return false;
        }

        if ($listing->status === 'published') {
            Log::info("Listing #{$listing->id} already published");
            return true;
        }

        // PUBLIER LE LISTING
        $listing->update([
            'status' => 'published',
            'published_at' => now()
        ]);

        Log::info("🚀 Listing #{$listing->id} AUTOMATICALLY PUBLISHED after successful payment #{$payment->id}");

        // Optionnel: Notification utilisateur
        $this->sendPublicationNotification($listing, $payment);

        return true;
    }



    /**
     * Notification optionnelle
     */
    private function sendPublicationNotification($listing, $payment)
    {
        try {
            // Ici vous pouvez ajouter:
            // - Envoi d'email
            // - Notification push
            // - Webhook vers votre frontend
            // - Etc.

            Log::info("Notification envoyée pour listing publié #{$listing->id}");
        } catch (\Exception $e) {
            Log::error("Échec d'envoi de notification: " . $e->getMessage());
        }
    }

    /**
     * API de retour après paiement
     */
    /**
     * API de retour après paiement - CORRIGÉE
     */
    /**
     * API de retour après paiement - VERSION AMÉLIORÉE
     */
    /**
     * API de retour après paiement - RETOURNE UNIQUEMENT LE STATUT
     */
    /**
     * API de retour après paiement PayTabs - VERSION COMPLÈTE
     */
    /**
     * API de retour après paiement PayTabs - VERSION COMPLÈTE
     */
    public function return(Request $request)
    {
        // LOG COMPLET pour déboguer
        Log::info('PayTabs Return - DEBUG COMPLET', [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'all_inputs' => $request->all(),
            'query_params' => $request->query(),
            'post_data' => $request->post(),
            'headers' => $request->headers->all(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip()
        ]);

        // EXTRACTION ROBUSTE DES PARAMÈTRES
        $tranRef = $request->input('tran_ref')
            ?? $request->query('tran_ref')
            ?? $request->input('tranRef')
            ?? $request->query('tranRef')
            ?? $request->input('transaction_ref')
            ?? $request->query('transaction_ref')
            ?? $request->input('reference')
            ?? $request->query('reference');

        $cartId = $request->input('cart_id')
            ?? $request->query('cart_id')
            ?? $request->input('cartId')
            ?? $request->query('cartId')
            ?? $request->input('cart')
            ?? $request->query('cart');

        $paymentId = $request->input('payment_id')
            ?? $request->query('payment_id')
            ?? $request->input('paymentId')
            ?? $request->query('paymentId');

        // STATUTS POSSIBLES DEPUIS PAYTABS
        $responseStatus = $request->input('response_status')
            ?? $request->query('response_status')
            ?? $request->input('status')
            ?? $request->query('status');

        $paymentResult = $request->input('payment_result')
            ?? $request->query('payment_result')
            ?? $request->input('result')
            ?? $request->query('result');

        Log::info('Paramètres extraits', [
            'tran_ref' => $tranRef,
            'cart_id' => $cartId,
            'payment_id' => $paymentId,
            'response_status' => $responseStatus,
            'payment_result' => $paymentResult
        ]);

        // RECHERCHE DU PAIEMENT
        $payment = null;

        // 1. Chercher par payment_id (le plus fiable)
        if ($paymentId) {
            $payment = Payment::with('listing')->find($paymentId);
            if ($payment) {
                Log::info("Paiement trouvé via payment_id: {$paymentId}");
            }
        }

        // 2. Chercher par tran_ref ou cart_id
        if (!$payment && ($tranRef || $cartId)) {
            $payment = Payment::where(function ($query) use ($tranRef, $cartId) {
                if ($tranRef) $query->where('tran_ref', $tranRef);
                if ($cartId) $query->orWhere('cart_id', $cartId);
            })->with('listing')->first();

            if ($payment) {
                Log::info("Paiement trouvé via tran_ref/cart_id");
            }
        }

        // 3. Si aucun paramètre, chercher le dernier paiement pending/initiated
        if (!$payment && !$tranRef && !$cartId && !$paymentId) {
            $payment = Payment::whereIn('payment_status', ['pending', 'initiated'])
                ->with('listing')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($payment) {
                Log::info("Paiement trouvé via dernier pending: #{$payment->id}");
            }
        }

        // VÉRIFICATION SI PAIEMENT TROUVÉ
        if (!$payment) {
            Log::error('Aucun paiement trouvé', [
                'tran_ref' => $tranRef,
                'cart_id' => $cartId,
                'payment_id' => $paymentId,
                'all_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Paiement non trouvé',
                'debug_info' => [
                    'tran_ref' => $tranRef,
                    'cart_id' => $cartId,
                    'payment_id' => $paymentId,
                    'received_params' => $request->all(),
                    'suggestion' => 'Vérifiez les paramètres envoyés par PayTabs'
                ]
            ], 404);
        }

        Log::info("Traitement du retour pour paiement #{$payment->id}", [
            'current_payment_status' => $payment->payment_status,
            'current_listing_status' => $payment->listing->status ?? 'no_listing',
            'payment_amount' => $payment->amount,
            'tran_ref' => $payment->tran_ref
        ]);

        // TRAITEMENT DU PAIEMENT
        $statusUpdated = false;

        // Si le paiement n'est pas encore finalisé
        if (!in_array($payment->payment_status, ['completed', 'failed'])) {

            // 1. Utiliser les paramètres de retour PayTabs si disponibles
            if ($responseStatus || $paymentResult) {
                Log::info("Utilisation des paramètres de retour PayTabs");

                $callbackData = [
                    'tran_ref' => $payment->tran_ref,
                    'cart_id' => $payment->cart_id,
                    'response_status' => $responseStatus,
                    'payment_result' => $paymentResult,
                    'response_message' => $request->input('response_message') ?? $request->query('response_message', ''),
                    'response_code' => $request->input('response_code') ?? $request->query('response_code', ''),
                ];

                $statusUpdated = $this->processCallbackData($payment, $callbackData);
            }

            // 2. Si pas de paramètres ou traitement échoué, vérifier avec API PayTabs
            if (!$statusUpdated && $payment->tran_ref) {
                Log::info("Vérification via API PayTabs pour tran_ref: {$payment->tran_ref}");

                $verificationResult = $this->verifyPayment($payment->tran_ref);

                if ($verificationResult) {
                    Log::info("Résultat de vérification PayTabs", $verificationResult);

                    $paymentResult = $verificationResult['payment_result'] ?? [];
                    $responseStatus = $paymentResult['response_status'] ?? '';
                    $responseMessage = $paymentResult['response_message'] ?? '';
                    $responseCode = $paymentResult['response_code'] ?? '';

                    $verifiedCallbackData = [
                        'tran_ref' => $payment->tran_ref,
                        'cart_id' => $payment->cart_id,
                        'response_status' => $responseStatus,
                        'response_code' => $responseCode,
                        'payment_result' => $responseStatus === 'A' ? 'completed' : 'failed',
                        'response_message' => $responseMessage,
                    ];

                    $statusUpdated = $this->processCallbackData($payment, $verifiedCallbackData);
                } else {
                    Log::warning("Impossible de vérifier le paiement via API PayTabs");
                }
            }
        }

        // ACTUALISER LE PAIEMENT
        $payment->refresh();

        // LOG DU RÉSULTAT FINAL
        Log::info("Résultat final du traitement", [
            'payment_id' => $payment->id,
            'payment_status' => $payment->payment_status,
            'listing_status' => $payment->listing->status ?? 'no_listing',
            'listing_published_at' => $payment->listing->published_at ?? null,
            'status_updated' => $statusUpdated
        ]);

        // CONSTRUCTION DE LA RÉPONSE
        $isSuccess = $payment->payment_status === 'completed';
        $isListingPublished = $payment->listing && $payment->listing->status === 'published';

        // CONSTRUCTION DE L'URL DE REDIRECTION
        $redirectParams = [
            'payment_id' => $payment->id,
            'status' => $payment->payment_status,
            'listing_id' => $payment->listing->id ?? null,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'SAR'
        ];

        if ($payment->tran_ref) {
            $redirectParams['tran_ref'] = $payment->tran_ref;
        }

        // Paramètres spécifiques selon le statut
        if ($isSuccess) {
            $redirectParams['success'] = 1;
            $redirectParams['published'] = $isListingPublished ? 1 : 0;
            $redirectParams['message'] = 'Paiement réussi';
        } else {
            $redirectParams['error'] = 'payment_' . $payment->payment_status;
            $redirectParams['message'] = 'Paiement échoué';
        }

        // URL de redirection selon l'environnement
        $baseUrl = app()->environment('local')
            ? 'https://dabapp.co/submission-success'
            : 'http://localhost:3000/submission-success';
        $redirectUrl = $baseUrl . '?' . http_build_query($redirectParams);

        Log::info("Redirection vers: {$redirectUrl}");

        // REDIRECTION OU RÉPONSE JSON
        if (!$request->ajax() && !$request->wantsJson()) {
            // Redirection HTML pour les navigateurs
            return redirect()->away($redirectUrl);
        }

        // Réponse JSON pour les requêtes AJAX/API avec URL de redirection
        return response()->json([
            'success' => $isSuccess,
            'message' => $this->getStatusMessage($payment->payment_status),
            'payment_status' => $payment->payment_status,
            'redirect_url' => $redirectUrl,
            'payment_details' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency ?? 'SAR',
                'tran_ref' => $payment->tran_ref,
                'cart_id' => $payment->cart_id,
                'created_at' => $payment->created_at,
                'completed_at' => $payment->completed_at,
                'failed_at' => $payment->failed_at,
                'response_code' => $payment->response_code,
                'payment_result' => $payment->payment_result
            ],
            'listing_details' => [
                'id' => $payment->listing->id ?? null,
                'title' => $payment->listing->title ?? null,
                'status' => $payment->listing->status ?? null,
                'published' => $isListingPublished,
                'published_at' => $payment->listing->published_at ?? null
            ],
            'summary' => [
                'payment_successful' => $isSuccess,
                'listing_published' => $isListingPublished,
                'ready_for_public' => $isSuccess && $isListingPublished,
                'status_was_updated' => $statusUpdated
            ],
            'debug_info' => [
                'received_tran_ref' => $tranRef,
                'received_cart_id' => $cartId,
                'received_payment_id' => $paymentId,
                'received_status' => $responseStatus,
                'received_result' => $paymentResult
            ]
        ]);
    }

    /**
     * Messages selon le statut
     */
    private function getStatusMessage($status)
    {
        switch ($status) {
            case 'completed':
                return 'SUCCÈS - Paiement confirmé et listing publié automatiquement';
            case 'failed':
                return 'ÉCHEC - Paiement échoué, listing restera en draft';
            case 'pending':
                return 'EN ATTENTE - Paiement en cours de traitement';
            case 'initiated':
                return 'INITIÉ - Paiement initié mais pas encore confirmé';
            default:
                return 'INCONNU - Statut de paiement : ' . $status;
        }
    }

    /**
     * Messages selon le statut
     */

    /**
     * Messages détaillés selon le statut
     */
    private function getPaymentStatusMessage($paymentStatus, $isPublished)
    {
        switch ($paymentStatus) {
            case 'completed':
                return $isPublished
                    ? 'Paiement réussi ! Votre annonce a été publiée automatiquement.'
                    : 'Paiement réussi mais problème de publication. Contactez le support.';
            case 'failed':
                return 'Paiement échoué. Votre annonce reste en brouillon.';
            case 'pending':
                return 'Paiement en cours de traitement. Veuillez patienter.';
            case 'initiated':
                return 'Paiement initié mais pas encore finalisé.';
            default:
                return 'Statut de paiement inconnu. Veuillez contacter le support.';
        }
    }

    private function buildRedirectUrl($params)
    {
        // URL de base selon l'environnement
        $baseUrl = app()->environment('local')
            ? 'http://localhost:3000/submission-success'  // Frontend local
            : 'https://dabapp.co/submission-success';     // Frontend production

        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Méthode auxiliaire pour redirection simple
     */
    private function redirectToHome($queryString = '')
    {
        $baseUrl = app()->environment('local')
            ? 'http://localhost:3000/submission-success'
            : 'https://dabapp.co/submission-success';

        $redirectUrl = $queryString ? $baseUrl . '?' . $queryString : $baseUrl;

        return redirect()->away($redirectUrl);
    }




    public function testCallback(Request $request)
    {
        Log::info('Test Callback - Server is reachable', [
            'timestamp' => now(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'data' => $request->all()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Callback endpoint is working',
            'timestamp' => now()
        ]);
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
