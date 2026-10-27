<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\Payment;
use App\Services\PayTabsConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Marketplace - Order payments",
 *     description="PayTabs server-to-server webhook and browser return for marketplace orders. Called by PayTabs directly, never by the app — no auth."
 * )
 */
class OrderPaymentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/marketplace/orders/paytabs/callback",
     *     summary="PayTabs server-to-server webhook",
     *     tags={"Marketplace - Order payments"},
     *     @OA\Response(response=200, description="Processed")
     * )
     */
    public function callback(Request $request)
    {
        Log::info('Marketplace order PayTabs callback received', $request->all());

        $tranRef = $request->input('tran_ref');
        $cartId = $request->input('cart_id');

        $payment = $this->findPayment($tranRef, $cartId);
        if (! $payment) {
            Log::error('Marketplace order PayTabs callback: payment not found', ['tran_ref' => $tranRef, 'cart_id' => $cartId]);

            return response()->json(['error' => 'Payment not found'], 404);
        }

        $this->verify($payment);

        $payment->refresh();

        return response()->json(['success' => $payment->payment_status === 'completed', 'payment_status' => $payment->payment_status]);
    }

    /**
     * @OA\Get(
     *     path="/api/marketplace/orders/paytabs/return",
     *     summary="Browser redirect after payment",
     *     tags={"Marketplace - Order payments"},
     *     @OA\Response(response=302, description="Redirects to the frontend order page")
     * )
     */
    public function return(Request $request)
    {
        $tranRef = $request->input('tran_ref') ?? $request->query('tran_ref');
        $cartId = $request->input('cart_id') ?? $request->query('cart_id');

        $payment = $this->findPayment($tranRef, $cartId);
        if (! $payment) {
            Log::error('Marketplace order PayTabs return: payment not found', ['tran_ref' => $tranRef, 'cart_id' => $cartId]);

            return redirect()->away($this->frontendUrl() . '/orders');
        }

        $this->verify($payment);

        $payment->refresh()->loadMissing('order');
        $order = $payment->order;

        return redirect()->away($this->frontendUrl() . '/orders/' . ($order?->order_number ?? ''));
    }

    // ------------------------------------------------------------------ internals

    private function findPayment(?string $tranRef, ?string $cartId): ?Payment
    {
        if (! $tranRef && ! $cartId) {
            return null;
        }

        return Payment::where(function ($q) use ($tranRef, $cartId) {
            if ($tranRef) {
                $q->where('tran_ref', $tranRef);
            }
            if ($cartId) {
                $q->orWhere('cart_id', $cartId);
            }
        })->with('order')->first();
    }

    private function verify(Payment $payment): void
    {
        if (in_array($payment->payment_status, ['completed', 'failed'], true)) {
            return;
        }

        if (! $payment->tran_ref) {
            Log::warning('Marketplace order PayTabs: no tran_ref on payment yet, nothing to verify', ['payment_id' => $payment->id]);

            return;
        }

        $result = PayTabsConfigService::verifyTransaction($payment->tran_ref);
        if (! $result) {
            Log::error('Marketplace order PayTabs: verification failed', ['payment_id' => $payment->id]);

            return;
        }

        $responseStatus = $result['payment_result']['response_status'] ?? '';

        if ($responseStatus === 'A') {
            $this->markCompleted($payment);
        } elseif (in_array($responseStatus, ['D', 'F', 'E'], true)) {
            $this->markFailed($payment, $result['payment_result']['response_message'] ?? 'Payment declined');
        }
    }

    private function markCompleted(Payment $payment): void
    {
        if ($payment->payment_status === 'completed') {
            return;
        }

        $payment->update(['payment_status' => 'completed', 'paid_at' => now()]);

        $order = $payment->order;
        if ($order && $order->status === Order::STATUS_PENDING_PAYMENT) {
            $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);
        }
    }

    private function markFailed(Payment $payment, string $reason): void
    {
        if (in_array($payment->payment_status, ['completed', 'failed'], true)) {
            return;
        }

        $payment->update(['payment_status' => 'failed', 'failure_reason' => $reason]);
    }

    private function frontendUrl(): string
    {
        return rtrim(config('app.frontend_url') ?: 'https://dabapp.co', '/');
    }
}
