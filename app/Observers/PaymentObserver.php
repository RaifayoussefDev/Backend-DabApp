<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * PayTabs marks a listing payment failed from several places (creation error,
 * callback, return URL, API verification, mobile verify) — notify once, on the
 * transition, instead of in each of them.
 */
class PaymentObserver
{
    public function updated(Payment $payment): void
    {
        if (!$payment->wasChanged('payment_status')
            || $payment->payment_status !== 'failed'
            || $payment->getOriginal('payment_status') === 'failed'
            || !$payment->user) {
            return;
        }

        try {
            app(NotificationService::class)->notifyPaymentFailed(
                $payment->user,
                $payment,
                $payment->payment_result
            );
        } catch (\Throwable $e) {
            Log::error("payment_failed notification failed for payment {$payment->id}: " . $e->getMessage());
        }
    }
}
