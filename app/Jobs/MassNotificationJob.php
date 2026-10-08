<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\NotificationToken;
use App\Models\User;
use App\Models\NotificationBatch;
use App\Models\NotificationLog;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MassNotificationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Broadcasting to a large audience is network-bound on FCM. Give the job
     * plenty of room and never retry it — a retry restarts handle() from the top
     * and re-notifies everyone it already reached. Recovery is manual (re-send).
     */
    public int $timeout = 1800;
    public int $tries = 1;

    /** Auto-release the ShouldBeUnique lock even if the job dies without cleanup. */
    public int $uniqueFor = 7200;

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

    /** One in-flight job per batch — a second worker can't double-send this broadcast. */
    public function uniqueId(): string
    {
        return (string) $this->batchId;
    }

    /**
     * Execute the job. Never accumulates a per-user list in memory — only running counts,
     * flushed to the NotificationBatch row once per chunk. Push goes out in FCM batch
     * calls (up to 500 messages each) rather than one HTTP request per token.
     */
    public function handle(NotificationService $notifications, FirebaseService $firebase)
    {
        $batch = NotificationBatch::find($this->batchId);
        if (!$batch) {
            Log::error("MassNotificationJob: batch {$this->batchId} not found, aborting.");
            return;
        }

        $batch->update(['status' => 'processing']);

        // For a 'both' broadcast the guest job also runs and owns the terminal
        // state + the combined total_targeted the controller already stored — this
        // job must not overwrite either.
        $sharedWithGuestJob = ($batch->audience ?? 'users') === 'both';

        $wantsPush  = in_array('push', $this->channels, true);
        $wantsEmail = in_array('email', $this->channels, true);

        $query = User::query()->applyFilters($this->filters);
        $totalUsers = $query->count();

        if ($totalUsers === 0) {
            if (!$sharedWithGuestJob) {
                $batch->update(['status' => 'completed', 'total_targeted' => 0, 'completed_at' => now()]);
            }
            return;
        }

        Log::info("MassNotificationJob: starting batch {$batch->id} for {$totalUsers} users.");

        $query->with('notificationPreference')
            ->chunkById(500, function ($users) use ($notifications, $firebase, $batch, $wantsPush, $wantsEmail) {
                $pushItems      = [];   // flat list for FirebaseService::sendBatch()
                $reachedUserIds = [];   // users reached via email this chunk (push resolved after send)
                $chunkCount     = $users->count();

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
                        if (!empty($this->content['action_url'])) {
                            $data['action_url'] = $this->content['action_url'];
                        }

                        $notification = $notifications->createBroadcastNotification(
                            $user, $title, $message, $data, $this->adminId, $batch->id
                        );

                        $pref = $user->notificationPreference;

                        if ($wantsPush && $pref && $pref->canSendPush()) {
                            foreach ($notifications->broadcastPushItemsFor($notification, $user) as $item) {
                                $pushItems[] = $item;
                            }
                        }

                        if ($wantsEmail && $pref && $pref->canSendEmail() && $user->email) {
                            // Queued, not sent inline — 5000 synchronous SMTP sends would
                            // blow the job timeout on their own.
                            Mail::to($user->email)->queue(new NotificationMail($notification, $message, $data));
                            $reachedUserIds[$user->id] = true;
                        }
                    } catch (\Throwable $e) {
                        Log::error("MassNotificationJob: failed to prepare user {$user->id}: " . $e->getMessage());
                    }
                }

                // Single batched push send for the whole chunk.
                $report = $firebase->sendBatch(array_map(
                    fn ($i) => \Illuminate\Support\Arr::except($i, '_meta'),
                    $pushItems
                ));

                $logRows      = [];
                $sentNotifIds = [];
                $now          = now();

                foreach ($pushItems as $item) {
                    $meta = $item['_meta'];
                    $ok   = $report['results'][$item['token']] ?? false;

                    $logRows[] = [
                        'notification_id' => $meta['notification_id'],
                        'user_id'         => $meta['user_id'],
                        'channel'         => 'push',
                        'fcm_token'       => $item['token'],
                        'device_type'     => $meta['device_type'],
                        'device_id'       => $meta['device_id'],
                        'status'          => $ok ? 'sent' : 'failed',
                        'error_message'   => $ok ? null : 'Broadcast push not delivered',
                        'queued_at'       => $now,
                        'sent_at'         => $ok ? $now : null,
                        'failed_at'       => $ok ? null : $now,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];

                    if ($ok) {
                        $reachedUserIds[$meta['user_id']] = true;
                        $sentNotifIds[$meta['notification_id']] = true;
                    }
                }

                if ($logRows) {
                    foreach (array_chunk($logRows, 500) as $slice) {
                        NotificationLog::insert($slice);
                    }
                }

                if ($sentNotifIds) {
                    \App\Models\Notification::whereIn('id', array_keys($sentNotifIds))
                        ->update(['push_sent' => true, 'push_sent_at' => $now]);
                }

                if (!empty($report['invalid_tokens'])) {
                    NotificationToken::whereIn('fcm_token', array_unique($report['invalid_tokens']))
                        ->update(['is_active' => false]);
                }

                $reached = count($reachedUserIds);
                $batch->increment('sent_count', $reached);
                $batch->increment('failed_count', max(0, $chunkCount - $reached));
            }, 'users.id', 'id');

        if ($sharedWithGuestJob) {
            // Guest job finalises the batch; keep the controller's combined total.
            Log::info("MassNotificationJob: batch {$batch->id} user side done (guest side still owns completion).");
        } else {
            $batch->update([
                'total_targeted' => $totalUsers,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        Log::info("MassNotificationJob: batch {$batch->id} completed.", [
            'sent' => $batch->fresh()->sent_count,
            'failed' => $batch->fresh()->failed_count,
        ]);
    }

    /** Never leave the batch stuck on 'processing' if the job throws. */
    public function failed(\Throwable $e): void
    {
        $batch = NotificationBatch::find($this->batchId);

        if ($batch && !in_array($batch->status, ['completed', 'cancelled'], true)
            && ($batch->audience ?? 'users') !== 'both') {
            $batch->update(['status' => 'failed']);
        }

        Log::error("MassNotificationJob: batch {$this->batchId} failed: " . $e->getMessage());
    }
}
