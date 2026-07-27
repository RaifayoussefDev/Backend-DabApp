<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\NotificationBatch;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class MassNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $batchId;
    protected array $filters;
    protected array $content;
    protected array $channels;
    protected ?int $adminId;

    /**
     * @param int $batchId The NotificationBatch row already created by the controller (holds aggregate counters).
     * @param array $filters ['country_id', 'category_id', 'date_from', 'date_to', ...]
     * @param array $content ['title_en', 'title_ar', 'body_en', 'body_ar', 'type']
     * @param array $channels ['push', 'email']
     * @param int|null $adminId The admin who triggered the send — auth()->id() is unavailable once this runs on a queue worker.
     */
    public function __construct(int $batchId, array $filters, array $content, array $channels, ?int $adminId = null)
    {
        $this->batchId = $batchId;
        $this->filters = $filters;
        $this->content = $content;
        $this->channels = $channels;
        $this->adminId = $adminId;
    }

    /**
     * Execute the job. Never accumulates a per-user list in memory — only running counts,
     * flushed to the NotificationBatch row once per chunk so this scales to any user count.
     */
    public function handle(NotificationService $notificationService)
    {
        $batch = NotificationBatch::find($this->batchId);
        if (!$batch) {
            Log::error("MassNotificationJob: batch {$this->batchId} not found, aborting.");
            return;
        }

        $batch->update(['status' => 'processing']);

        $query = User::query()->applyFilters($this->filters);
        $totalUsers = $query->count();

        if ($totalUsers === 0) {
            $batch->update(['status' => 'completed', 'total_targeted' => 0, 'completed_at' => now()]);
            return;
        }

        Log::info("MassNotificationJob: starting batch {$batch->id} for {$totalUsers} users.");

        // Process in chunks to bound memory regardless of how many users match the filter.
        $query->chunk(200, function ($users) use ($notificationService, $batch) {
            $sentInChunk = 0;
            $failedInChunk = 0;

            foreach ($users as $user) {
                try {
                    $lang = $user->language ?? 'en';
                    $title = ($lang === 'ar' && !empty($this->content['title_ar']))
                        ? $this->content['title_ar']
                        : ($this->content['title_en'] ?? 'Notification');

                    $message = ($lang === 'ar' && !empty($this->content['body_ar']))
                        ? $this->content['body_ar']
                        : ($this->content['body_en'] ?? '');

                    $data = [
                        'type' => $this->content['type'] ?? 'info',
                        'original_content' => $this->content,
                    ];

                    $result = $notificationService->sendCustomNotification($user, $title, $message, $data, [
                        'channels' => $this->channels,
                        'priority' => 'high',
                        'batch_id' => $batch->id,
                        'sent_by_admin' => $this->adminId,
                    ]);

                    $pushSent = isset($result['push_results']['sent']) && $result['push_results']['sent'] > 0;
                    $emailSent = ($result['email_result'] ?? null) === 'sent';

                    if ($pushSent || $emailSent) {
                        $sentInChunk++;
                    } else {
                        $failedInChunk++;
                    }
                } catch (\Exception $e) {
                    $failedInChunk++;
                    Log::error("MassNotificationJob: failed to notify user {$user->id}: " . $e->getMessage());
                }
            }

            // One UPDATE per chunk (200 users), never one per user.
            $batch->increment('sent_count', $sentInChunk);
            $batch->increment('failed_count', $failedInChunk);
        });

        $batch->update([
            'total_targeted' => $totalUsers,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Log::info("MassNotificationJob: batch {$batch->id} completed.", [
            'sent' => $batch->fresh()->sent_count,
            'failed' => $batch->fresh()->failed_count,
        ]);
    }
}
