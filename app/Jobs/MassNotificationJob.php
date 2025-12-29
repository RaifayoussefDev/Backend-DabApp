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
        Log::info("MassNotificationJob: STARTED", ['filters' => $this->filters, 'content' => $this->content]);

        $query = User::query()->where('is_active', true);

        // 1. Filter by City (Users who have listings in this city)
        if (!empty($this->filters['city_id'])) {
            $query->whereHas('listings', function($q) {
                $q->where('city_id', $this->filters['city_id']);
            });
            Log::info("MassNotificationJob: Filtered by city_id: " . $this->filters['city_id']);
        }

        // 2. Filter by Listing Category or Date
        if (!empty($this->filters['category_id']) || !empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $query->whereHas('listings', function($q) {
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
            Log::info("MassNotificationJob: Applied extra filters");
        }

        $totalUsers = $query->count();
        Log::info("MassNotificationJob: Found {$totalUsers} users matching criteria.");

        if ($totalUsers === 0) {
            Log::warning("MassNotificationJob: No users found. Job ending.");
            return;
        }

        $query->chunk(100, function ($users) use ($notificationService) {
            Log::info("MassNotificationJob: Processing chunk of " . $users->count() . " users.");
            foreach ($users as $user) {
                try {
                    $data = [
                        'title' => $this->content['title_en'] ?? 'Notification',
                        'title_ar' => $this->content['title_ar'] ?? ($this->content['title_en'] ?? 'Notification'),
                        'body' => $this->content['body_en'] ?? '',
                        'body_ar' => $this->content['body_ar'] ?? ($this->content['body_en'] ?? ''),
                    ];

                    Log::info("MassNotificationJob: Sending to User ID {$user->id}");
                    
                    $result = $notificationService->sendToUser($user, 'admin_broadcast', $data, [
                        'channels' => $this->channels, 
                        'priority' => 'high'
                    ]);

                    Log::info("MassNotificationJob: Result for User {$user->id}: " . json_encode($result));

                } catch (\Exception $e) {
                    Log::error("MassNotificationJob: Failed for user {$user->id}: " . $e->getMessage());
                }
            }
        });

        Log::info("MassNotificationJob: COMPLETED.");
    }
}
