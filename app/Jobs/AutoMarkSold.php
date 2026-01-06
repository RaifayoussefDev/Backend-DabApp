<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Listing;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\SoomController;

class AutoMarkSold implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        Log::info('Starting auto-mark-sold check...');

        // Find listings that are PUBLISHED but have a validated sale > 5 days ago
        // We find submissions first
        $validatedSubmissions = Submission::where('sale_validated', true)
            ->where('sale_validation_date', '<', now()->subDays(5))
            ->whereHas('listing', function ($q) {
                // Ensure listing is still published (not yet marked as sold)
                $q->where('status', 'published');
            })
            ->with('listing')
            ->get();

        Log::info("Found {$validatedSubmissions->count()} validated sales with pending listing status.");

        $controller = app(SoomController::class);

        foreach ($validatedSubmissions as $submission) {
            try {
                $listing = $submission->listing;
                Log::info("Auto-marking listing {$listing->id} as sold (Sale validated on {$submission->sale_validation_date})");

                // Call the controller method to ensure consistent logic (notifications etc.)
                // We mock a request or extract logic. calling controller method directly with mock request.
                // Since markListingAsSold uses Request object for validation (though minimal), we can simulate it or just call logic.
                // Ideally, we should refactor logic to Service, but for now we mimic the Controller call.

                // Construct a dummy request if needed, but the method seems to check Auth which might fail in Console.
                // WAIT! Controller uses Auth::id(). In Job running in console, Auth::id() is null.
                // Controller: if (!$userId) return 401.
                // So calling controller method directly WON'T WORK easily without bypassing Auth.

                // REPLICATING LOGIC instead of calling controller.

                // 1. Mark listing as sold
                $listing->update([
                    'status' => 'sold',
                    'allow_submission' => false
                ]);

                // 2. Reject pending submissions
                Submission::where('listing_id', $listing->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'rejected',
                        'rejection_reason' => 'Listing automatically marked as sold (Auto-close after validation)'
                    ]);

                // 3. Notification (logic copied/adapted from Controller)
                // We need NotificationService
                $notificationService = app(\App\Services\NotificationService::class);

                // Notify users who were pending (and now rejected)
                // Also notify the BUYER of the validated sale? No, they won. They know.
                // We notify LOSING bidders.
                $pendingSoomUsers = Submission::where('listing_id', $listing->id)
                    ->where('status', 'rejected')
                    ->where('rejection_reason', 'Listing automatically marked as sold (Auto-close after validation)')
                    ->with('user')
                    ->get();

                foreach ($pendingSoomUsers as $rejectedSubmission) {
                    try {
                        $notificationService->notifyListingSold($rejectedSubmission->user, $listing);
                        Log::info('Sent Auto-Listing Sold notification to user ' . $rejectedSubmission->user_id);
                    } catch (\Exception $e) {
                        Log::error('Failed to send notification: ' . $e->getMessage());
                    }
                }

                Log::info("✅ Listing {$listing->id} auto-marked as sold.");

            } catch (\Exception $e) {
                Log::error("Failed to auto-mark listing {$submission->listing_id} as sold: " . $e->getMessage());
            }
        }
    }
}
