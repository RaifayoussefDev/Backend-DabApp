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
use App\Traits\UserFilterTrait;

class MassNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UserFilterTrait;

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

        $query = $this->buildFilteredUserQuery($this->filters);

        $totalUsers = $query->count();
        Log::info("Found {$totalUsers} users for mass notification.");

        $count = $query->count();
        Log::info("MassNotificationJob: Found {$count} users matching filters.", ['filters' => $this->filters]);

        if ($count === 0) {
            Log::warning("MassNotificationJob: No users found matching the criteria.");
            return;
        }

        $query->chunk(100, function ($users) use ($notificationService) {
            /** @var User $user */
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
