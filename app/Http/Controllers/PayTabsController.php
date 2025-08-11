<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PayTabsService;
use App\Models\Payment;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PayTabsController extends Controller
{
    protected $payTabsService;

    public function __construct(PayTabsService $payTabsService)
    {
        $this->payTabsService = $payTabsService;
    }

    /**
     * Test de connexion PayTabs
     */
    public function testConnection()
    {
        $result = $this->payTabsService->testConnection();

        return response()->json([
            'paytabs_status' => $result['success'] ? 'Working' : 'Not Working',
            'message' => $result['message'],
            'config' => $this->payTabsService->getConfig(),
            'details' => $result
        ], $result['success'] ? 200 : 500);
    }

    /**
     * Initier un paiement pour un listing
     */
    public function initiatePayment(Request $request, $listingId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $listing = Listing::find($listingId);
            if (!$listing) {
                return response()->json(['message' => 'Listing not found'], 404);
            }

            // Vérifier si le listing nécessite un paiement
            if ($listing->status !== 'pending_payment' && $listing->status !== 'published') {
                return response()->json(['message' => 'Listing is not available for payment'], 400);
            }

            // Calculer le montant (frais de publication ou prix de l'item)
            $amount = $request->input('amount') ?? $this->calculatePaymentAmount($listing);

            if ($amount <= 0) {
                return response()->json(['message' => 'Invalid payment amount'], 400);
            }

            // Créer le paiement dans PayTabs
            $paymentResult = $this->payTabsService->createPaymentForListing($listing, $user, $amount);

            if ($paymentResult['success']) {
                // Enregistrer le paiement en base (statut pending)
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'listing_id' => $listing->id,
                    'amount' => $amount,
                    'payment_method_id' => null,
                    'bank_card_id' => null,
                    'payment_status' => 'pending',
                    'tran_ref' => $paymentResult['tran_ref'] ?? null,
                    'cart_id' => $paymentResult['cart_id'] ?? null,
                ]);

                Log::info('Payment initiated', [
                    'payment_id' => $payment->id,
                    'listing_id' => $listing->id,
                    'user_id' => $user->id,
                    'amount' => $amount
                ]);

                return response()->json([
                    'success' => true,
                    'payment_url' => $paymentResult['payment_url'],
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'message' => 'Payment initiated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'],
                    'error' => $paymentResult['error'] ?? null
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Payment Initiation Error', [
                'listing_id' => $listingId,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Callback PayTabs (webhook)
     */
    public function callback(Request $request)
    {
        try {
            Log::info('PayTabs Callback Received', $request->all());

            $tranRef = $request->input('tran_ref');
            $cartId = $request->input('cart_id');
            $respStatus = $request->input('resp_status');
            $respCode = $request->input('resp_code');
            $respMessage = $request->input('resp_message');

            if (!$tranRef || !$cartId) {
                Log::error('PayTabs Callback Missing Data', $request->all());
                return response()->json(['status' => 'error', 'message' => 'Missing required data']);
            }

            // Vérifier le paiement avec PayTabs
            $verificationResult = $this->payTabsService->verifyPayment($tranRef);

            DB::beginTransaction();

            try {
                // Trouver le paiement en base
                $payment = Payment::where('tran_ref', $tranRef)
                    ->orWhere('cart_id', $cartId)
                    ->first();

                if ($payment) {
                    // Mettre à jour le statut du paiement
                    $paymentStatus = ($respStatus === 'A' && $respCode === '100') ? 'completed' : 'failed';

                    $payment->update([
                        'payment_status' => $paymentStatus,
                        'resp_code' => $respCode,
                        'resp_message' => $respMessage,
                        'verification_data' => $verificationResult['data'] ?? []
                    ]);

                    // Si le paiement est réussi, mettre à jour le listing
                    if ($paymentStatus === 'completed' && $payment->listing) {
                        $payment->listing->update(['status' => 'published']);
                    }

                    Log::info('PayTabs Payment Updated', [
                        'payment_id' => $payment->id,
                        'status' => $paymentStatus,
                        'tran_ref' => $tranRef
                    ]);
                } else {
                    Log::error('PayTabs Payment Not Found', [
                        'tran_ref' => $tranRef,
                        'cart_id' => $cartId
                    ]);
                }

                DB::commit();

                return response()->json(['status' => 'success']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('PayTabs Callback Error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json(['status' => 'error', 'message' => 'Callback processing failed'], 500);
        }
    }

    /**
     * Return URL après paiement (redirection utilisateur)
     */
    public function return(Request $request)
    {
        try {
            $tranRef = $request->input('tran_ref');
            $respStatus = $request->input('resp_status');
            $respCode = $request->input('resp_code');

            Log::info('PayTabs Return URL accessed', [
                'tran_ref' => $tranRef,
                'resp_status' => $respStatus,
                'resp_code' => $respCode
            ]);

            $payment = Payment::where('tran_ref', $tranRef)->first();

            if ($payment) {
                $success = ($respStatus === 'A' && $respCode === '100');

                if ($success) {
                    // Rediriger vers une page de succès ou le frontend
                    return redirect()->to(env('FRONTEND_URL', '/') . '/payment/success?payment_id=' . $payment->id)
                        ->with('success', 'Payment completed successfully!');
                } else {
                    // Rediriger vers une page d'échec
                    return redirect()->to(env('FRONTEND_URL', '/') . '/payment/failed?payment_id=' . $payment->id)
                        ->with('error', 'Payment failed. Please try again.');
                }
            }

            return redirect()->to(env('FRONTEND_URL', '/'))->with('error', 'Payment information not found.');
        } catch (\Exception $e) {
            Log::error('PayTabs Return Error', ['error' => $e->getMessage()]);
            return redirect()->to(env('FRONTEND_URL', '/'))->with('error', 'An error occurred processing your payment.');
        }
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function checkPaymentStatus(Request $request, $paymentId)
    {
        try {
            $user = Auth::user();
            $payment = Payment::where('id', $paymentId)
                              ->where('user_id', $user->id)
                              ->with(['listing:id,title,status'])
                              ->first();

            if (!$payment) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            // Vérifier avec PayTabs si le paiement est encore pending
            if ($payment->payment_status === 'pending' && $payment->tran_ref) {
                $verificationResult = $this->payTabsService->verifyPayment($payment->tran_ref);

                if ($verificationResult['success']) {
                    $payTabsData = $verificationResult['data'];
                    $respStatus = $payTabsData['payment_result']['response_status'] ?? '';
                    $respCode = $payTabsData['payment_result']['response_code'] ?? '';

                    $newStatus = ($respStatus === 'A' && $respCode === '100') ? 'completed' :
                               ($respStatus === 'D' ? 'failed' : 'pending');

                    if ($newStatus !== 'pending') {
                        DB::beginTransaction();
                        try {
                            $payment->update([
                                'payment_status' => $newStatus,
                                'resp_code' => $respCode,
                                'resp_message' => $payTabsData['payment_result']['response_message'] ?? '',
                                'verification_data' => $payTabsData
                            ]);

                            // Publier le listing si paiement réussi
                            if ($newStatus === 'completed' && $payment->listing) {
                                $payment->listing->update(['status' => 'published']);
                            }

                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            throw $e;
                        }
                    }
                }
            }

            return response()->json([
                'payment_id' => $payment->id,
                'status' => $payment->payment_status,
                'amount' => $payment->amount,
                'listing' => $payment->listing,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
                'resp_message' => $payment->resp_message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error checking payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer le montant du paiement selon le type
     */
    private function calculatePaymentAmount($listing)
    {
        // Logique pour déterminer les frais selon le type d'annonce
        $fees = [
            1 => 10.00, // Motorcycles
            2 => 5.00,  // Spare parts
            3 => 15.00, // License plates
        ];

        return $fees[$listing->category_id] ?? 0.00;
    }
}
