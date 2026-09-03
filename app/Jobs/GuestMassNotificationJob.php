<?php

namespace App\Jobs;

use App\Models\GuestNotificationToken;
use App\Models\NotificationBatch;
use App\Models\NotificationToken;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Guest-audience counterpart of MassNotificationJob. Sends a single admin
 * broadcast to guest (not-logged-in) device tokens. Push only — a guest has no
 * account, no email and no notification preferences, so there is nothing to
 * persist per recipient; only the aggregate counters on the NotificationBatch
 * row are updated (same "never lists individual recipients" contract as the
 * user broadcast).
 */
class GuestMassNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $batchId;
    protected array $filters;
    protected array $content;

    /**
     * @param int   $batchId The NotificationBatch row created by the controller.
     * @param array $filters ['device_type','app_version','country_id','city_id','active_since','viewed_category_id','viewed_listing_id']
     * @param array $content ['title_en','title_ar','body_en','body_ar','type']
     */
    public function __construct(int $batchId, array $filters, array $content)
    {
        $this->batchId = $batchId;
        $this->filters = $filters;
        $this->content = $content;
    }

    public function handle(FirebaseService $firebase): void
    {
        $batch = NotificationBatch::find($this->batchId);
        if (!$batch) {
            Log::error("GuestMassNotificationJob: batch {$this->batchId} not found, aborting.");
            return;
        }

        // Only flip to processing if the user side hasn't already (audience = both).
        if (!in_array($batch->status, ['processing', 'completed'], true)) {
            $batch->update(['status' => 'processing']);
        }

        $query = $this->buildQuery();
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->finish($batch);
            return;
        }

        // fcm_tokens already owned by a logged-in user — the guest copy would be a
        // duplicate delivery, so skip them.
        $registeredTokens = NotificationToken::where('is_active', true)
            ->pluck('fcm_token')
            ->flip();

        $type = $this->content['type'] ?? 'info';

        $query->chunkById(200, function ($tokens) use ($firebase, $batch, $registeredTokens, $type) {
            $sent = 0;
            $failed = 0;

            foreach ($tokens as $token) {
                if ($registeredTokens->has($token->fcm_token)) {
                    continue;
                }

                $isArabic = ($token->locale === 'ar');
                $title = $isArabic && !empty($this->content['title_ar'])
                    ? $this->content['title_ar']
                    : ($this->content['title_en'] ?? 'DabApp');
                $body = $isArabic && !empty($this->content['body_ar'])
                    ? $this->content['body_ar']
                    : ($this->content['body_en'] ?? '');

                $pushData = [
                    'type' => $type,
                    'audience' => 'guest',
                    'batch_id' => (string) $batch->id,
                    'timestamp' => now()->toIso8601String(),
                ];
                if (!empty($this->content['action_url'])) {
                    $pushData['action_url'] = (string) $this->content['action_url'];
                }

                try {
                    $result = $firebase->sendToToken(
                        $token->fcm_token,
                        $title,
                        $body,
                        $pushData,
                        ['priority' => 'high', 'sound' => 'default']
                    );

                    if ($result['success'] ?? false) {
                        $sent++;
                        $token->markNotified();
                        if ($token->failed_attempts > 0) {
                            $token->resetFailedAttempts();
                        }
                    } else {
                        $failed++;
                        $token->incrementFailedAttempts();
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error("GuestMassNotificationJob: send failed for guest token {$token->id}: " . $e->getMessage());
                }
            }

            $batch->increment('sent_count', $sent);
            $batch->increment('failed_count', $failed);
        });

        $this->finish($batch);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildQuery()
    {
        return GuestNotificationToken::query()->active()->matchingFilters($this->filters);
    }

    protected function finish(NotificationBatch $batch): void
    {
        // Guests own batch completion for both 'guests' and 'both' audiences
        // (MassNotificationJob defers to this job when audience = both).
        if (in_array($batch->audience, ['guests', 'both'], true)) {
            $batch->update(['status' => 'completed', 'completed_at' => now()]);
        }

        Log::info("GuestMassNotificationJob: batch {$batch->id} guest side done.", [
            'sent' => $batch->fresh()->sent_count,
            'failed' => $batch->fresh()->failed_count,
        ]);
    }
}
