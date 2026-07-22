<?php

namespace App\Console\Commands;

use App\Models\TrainerReview;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoApprovePendingTrainerReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trainer-reviews:auto-approve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-approve trainer reviews still pending admin moderation 24 hours after submission';

    /**
     * Grace period given to an admin to moderate a review before it auto-publishes.
     */
    private const MODERATION_WINDOW_HOURS = 24;

    public function handle(NotificationService $notifications): int
    {
        $cutoff = now()->subHours(self::MODERATION_WINDOW_HOURS);

        $reviews = TrainerReview::with('trainer')
            ->where('is_approved', false)
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($reviews as $review) {
            if (!$review->trainer) {
                continue;
            }

            $review->update(['is_approved' => true]);
            $review->trainer->recalculateRating();

            try {
                $notifications->notifyTrainerReviewApproved($review->trainer->user, $review->trainer);
            } catch (\Exception $e) {
                Log::error('trainer-reviews:auto-approve notify failed: ' . $e->getMessage());
            }
        }

        $this->info("Auto-approved {$reviews->count()} trainer review(s) pending 24h+.");

        Log::info('trainer-reviews:auto-approve finished', ['reviews_approved' => $reviews->count()]);

        return Command::SUCCESS;
    }
}
