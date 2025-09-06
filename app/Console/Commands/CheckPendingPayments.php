<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:check-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update pending PayTabs payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking pending payments...');

        // Récupérer les paiements "initiated" des dernières 24h
        $pendingPayments = Payment::where('payment_status', 'initiated')
            ->where('created_at', '>=', now()->subHours(24))
            ->whereNotNull('tran_ref')
            ->get();

        $this->info("Found {$pendingPayments->count()} pending payments");

        if ($pendingPayments->count() === 0) {
            $this->info('No pending payments to check.');
            return;
        }

        $completed = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($pendingPayments as $payment) {
            $this->info("Checking payment #{$payment->id} - tran_ref: {$payment->tran_ref}");

            // Vérifier avec PayTabs API
            $result = $this->verifyPaymentWithPayTabs($payment->tran_ref);

            if ($result) {
                $paymentResult = $result['payment_result'] ?? [];
                $status = $paymentResult['response_status'] ?? '';
                $message = $paymentResult['response_message'] ?? '';
                $code = $paymentResult['response_code'] ?? '';

                Log::info("PayTabs verification result for payment #{$payment->id}", [
                    'status' => $status,
                    'message' => $message,
                    'code' => $code
                ]);

                if ($status === 'A') {
                    // Paiement approuvé
                    $payment->update([
                        'payment_status' => 'completed',
                        'completed_at' => now(),
                        'payment_result' => $message ?: 'Payment approved',
                        'response_code' => $code
                    ]);

                    // Publier le listing
                    if ($payment->listing && $payment->listing->status !== 'published') {
                        $payment->listing->update([
                            'status' => 'published',
                            'published_at' => now()
                        ]);
                        $this->info("✅ Payment #{$payment->id} completed and listing #{$payment->listing->id} published");

                        Log::info("Payment completed and listing published", [
                            'payment_id' => $payment->id,
                            'listing_id' => $payment->listing->id
                        ]);
                    } else {
                        $this->info("✅ Payment #{$payment->id} completed");
                    }

                    $completed++;

                } elseif (in_array($status, ['D', 'F', 'E'])) {
                    // Paiement échoué
                    $payment->update([
                        'payment_status' => 'failed',
                        'failed_at' => now(),
                        'payment_result' => $message ?: 'Payment failed',
                        'response_code' => $code
                    ]);

                    $this->error("❌ Payment #{$payment->id} failed: {$message}");
                    $failed++;

                } else {
                    $this->warn("⏳ Payment #{$payment->id} still pending (status: {$status})");
                    $stillPending++;
                }
            } else {
                $this->error("🚫 Could not verify payment #{$payment->id} with PayTabs");
            }
        }

        $this->newLine();
        $this->info("=== SUMMARY ===");
        $this->info("✅ Completed: {$completed}");
        $this->info("❌ Failed: {$failed}");
        $this->info("⏳ Still Pending: {$stillPending}");

        Log::info('Pending payments check completed', [
            'total_checked' => $pendingPayments->count(),
            'completed' => $completed,
            'failed' => $failed,
            'still_pending' => $stillPending
        ]);

        return Command::SUCCESS;
    }

    /**
     * Verify payment status with PayTabs API
     */
    private function verifyPaymentWithPayTabs($tranRef)
    {
        try {
            $baseUrls = [
                'ARE' => 'https://secure.paytabs.com/',
                'SAU' => 'https://secure.paytabs.sa/',
                'OMN' => 'https://secure-oman.paytabs.com/',
                'JOR' => 'https://secure-jordan.paytabs.com/',
                'EGY' => 'https://secure-egypt.paytabs.com/',
                'GLOBAL' => 'https://secure-global.paytabs.com/'
            ];

            $region = config('paytabs.region', 'ARE');
            $baseUrl = $baseUrls[$region] ?? $baseUrls['ARE'];

            $payload = [
                'profile_id' => (int) config('paytabs.profile_id'),
                'tran_ref' => $tranRef
            ];

            Log::info("Verifying payment with PayTabs", [
                'tran_ref' => $tranRef,
                'endpoint' => $baseUrl . 'payment/query',
                'profile_id' => config('paytabs.profile_id')
            ]);

            $response = Http::withHeaders([
                'Authorization' => config('paytabs.server_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($baseUrl . 'payment/query', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("PayTabs verification response", [
                    'tran_ref' => $tranRef,
                    'response' => $data
                ]);
                return $data;
            }

            Log::error('PayTabs verification failed', [
                'tran_ref' => $tranRef,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('PayTabs verification error', [
                'tran_ref' => $tranRef,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
