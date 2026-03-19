<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class MassNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filters;
    protected $content;
    protected $channels;

    /**
     * Create a new job instance.
     *
     * @param array $filters ['city_id', 'category_id', 'date_from', 'date_to']
     * @param array $content ['title', 'title_ar', 'body', 'body_ar']
     * @param array $channels ['push', 'email']
     */
    public function __construct(array $filters, array $content, array $channels)
    {
        $this->filters = $filters;
        $this->content = $content;
        $this->channels = $channels;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService)
    {
        Log::info("Starting Mass Notification Job", ['filters' => $this->filters]);

        $query = User::query()->where('is_active', true);

        // 0. Filter by specific User IDs (Direct Notification)
        if (!empty($this->filters['user_ids'])) {
            $query->whereIn('id', $this->filters['user_ids']);
        }

        // 1. Filter by Country (Users who belong to this country)
        if (!empty($this->filters['country_id'])) {
            $query->where('country_id', $this->filters['country_id']);
        }

        // 2. Filter by Listing Criteria
        if (!empty($this->filters['category_id']) || !empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $query->whereHas('listings', function ($q) {
                if (!empty($this->filters['category_id'])) {
                    $q->where('category_id', $this->filters['category_id']);
                }
                if (!empty($this->filters['date_from'])) {
                    $q->where('created_at', '>=', $this->filters['date_from']);
                }
                if (!empty($this->filters['date_to'])) {
                    $q->where('created_at', '<=', $this->filters['date_to']);
                }
            });
        }

        // 3. Filter by "Has Listing" (Boolean)
        if (isset($this->filters['has_listing'])) {
            if ($this->filters['has_listing']) {
                $query->has('listings');
            } else {
                $query->doesntHave('listings');
            }
        }

        // 4. Filter by Brand in Garage
        if (!empty($this->filters['brand_in_garage'])) {
            $query->whereHas('myGarage', function ($q) {
                $q->where('brand_id', $this->filters['brand_in_garage']);
            });
        }

        $totalUsers = $query->count();
        Log::info("Found {$totalUsers} users for mass notification.");

        $count = $query->count();
        Log::info("MassNotificationJob: Found {$count} users matching filters.", ['filters' => $this->filters]);

        if ($count === 0) {
            Log::warning("MassNotificationJob: No users found matching the criteria.");
            return;
        }

        $query->chunk(100, function ($users) use ($notificationService) {
            foreach ($users as $user) {
                try {
                    // Determine title and message based on user preference
                    $lang = $user->language ?? 'en';
                    $title = ($lang === 'ar' && !empty($this->content['title_ar']))
                        ? $this->content['title_ar']
                        : ($this->content['title_en'] ?? 'Notification');

                    $message = ($lang === 'ar' && !empty($this->content['body_ar']))
                        ? $this->content['body_ar']
                        : ($this->content['body_en'] ?? '');

                    $data = [
                        'type' => $this->content['type'] ?? 'info',
                        'original_content' => $this->content
                    ];

                    $result = $notificationService->sendCustomNotification($user, $title, $message, $data, [
                        'channels' => $this->channels,
                        'priority' => 'high'
                    ]);

                    if (!$result['success']) {
                        Log::warning("Mass notification failed for user {$user->id}: " . ($result['message'] ?? 'Unknown error'));
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to send mass notification to user {$user->id}: " . $e->getMessage());
                }
            }
        });

        Log::info("Mass Notification Job Completed.");
    }
}
