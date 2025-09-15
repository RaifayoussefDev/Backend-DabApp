<?php

// app/Jobs/CheckExpiredValidations.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\ValidationExpiredMail;
use Illuminate\Support\Facades\Log;

class CheckExpiredValidations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        Log::info('Starting check for expired sale validations...');

        // Trouver toutes les soumissions acceptées il y a plus de 5 jours et non validées
        $expiredSubmissions = Submission::where('status', 'accepted')
            ->where('sale_validated', false)
            ->where('acceptance_date', '<', Carbon::now()->subDays(5))
            ->with(['listing.seller', 'user'])
            ->get();

        Log::info("Found {$expiredSubmissions->count()} expired submissions to process");

        $processedCount = 0;

        foreach ($expiredSubmissions as $submission) {
            try {
                // Marquer comme expiré et remettre en rejected
                $submission->update([
                    'status' => 'rejected',
                    'rejection_reason' => 'Sale validation period expired (5 days). The seller did not validate the sale within the required timeframe.',
                    'sale_validation_date' => now() // Marquer la date d'expiration
                ]);

                // Envoyer un email de notification à l'acheteur
                try {
                    Mail::to($submission->user->email)->send(new ValidationExpiredMail($submission));
                    Log::info("Sent expiration email to buyer: {$submission->user->email}");
                } catch (\Exception $e) {
                    Log::error("Failed to send validation expired email to {$submission->user->email}: " . $e->getMessage());
                }

                // Optionnel: Envoyer un email au vendeur aussi
                try {
                    Mail::to($submission->listing->seller->email)->send(new ValidationExpiredSellerMail($submission));
                    Log::info("Sent expiration notification to seller: {$submission->listing->seller->email}");
                } catch (\Exception $e) {
                    Log::error("Failed to send validation expired email to seller {$submission->listing->seller->email}: " . $e->getMessage());
                }

                $processedCount++;

            } catch (\Exception $e) {
                Log::error("Failed to process expired submission {$submission->id}: " . $e->getMessage());
            }
        }

        Log::info("Processed {$processedCount} expired validations successfully");

        return $processedCount;
    }
}
