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

        $query = User::query()->applyFilters($this->filters);

        $totalUsers = $query->count();
        Log::info("Found {$totalUsers} users for mass notification.");

        if ($totalUsers === 0) {
            Log::warning("MassNotificationJob: No users found matching the criteria.");
            return [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'message' => 'No users found matching the criteria'
            ];
        }

        $summary = [
            'total' => $totalUsers,
            'sent' => 0,
            'failed' => 0,
            'failed_details' => []
        ];

        // Process in chunks to avoid memory issues
        $query->chunk(100, function ($users) use ($notificationService, &$summary) {
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

                    // Check push results for accurate summary
                    $pushSent = isset($result['push_results']['sent']) && $result['push_results']['sent'] > 0;
                    $emailSent = isset($result['email_result']) && $result['email_result'] === 'sent';

                    if ($pushSent || $emailSent) {
                        $summary['sent']++;
                    } else {
                        $summary['failed']++;
                        $summary['failed_details'][] = [
                            'user_id' => $user->id,
                            'error' => $result['push_results']['message'] ?? ($result['email_result'] ?? 'No delivery channel succeeded')
                        ];
                    }

                } catch (\Exception $e) {
                    $summary['failed']++;
                    $summary['failed_details'][] = [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ];
                    Log::error("Failed to send mass notification to user {$user->id}: " . $e->getMessage());
                }
            }
        });

        Log::info("Mass Notification Job Completed.", ['summary' => $summary]);

        return $summary;
    }
}
