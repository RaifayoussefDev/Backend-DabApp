<?php

namespace App\Jobs;

use App\Models\Guide;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotifyPublishedGuideJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $guide;

    /**
     * Create a new job instance.
     */
    public function __construct(Guide $guide)
    {
        $this->guide = $guide;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info("Starting NotifyPublishedGuideJob for guide: {$this->guide->id}");

        // Find all active users and notify them in chunks
        User::active()->chunk(100, function ($users) use ($notificationService) {
            foreach ($users as $user) {
                try {
                    $notificationService->sendToUser($user, 'new_guide_published', [
                        'guide_id' => $this->guide->id,
                        'guide_title' => $this->guide->title,
                        'excerpt' => Str::limit(strip_tags($this->guide->excerpt), 100),
                    ], [
                        'entity' => $this->guide,
                        'priority' => 'normal',
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to notify user {$user->id} for guide published {$this->guide->id}: " . $e->getMessage());
                }
            }
        });

        Log::info("Finished NotifyPublishedGuideJob for guide: {$this->guide->id}");
    }
}
