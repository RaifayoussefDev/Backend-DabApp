<?php

namespace App\Jobs;

use App\Models\GuestNotificationToken;
use App\Models\NotificationBatch;
use App\Models\NotificationToken;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Guest-audience counterpart of MassNotificationJob. Sends a single admin
 * broadcast to guest (not-logged-in) device tokens. Push only — a guest has no
 * account, no email and no notification preferences, so there is nothing to
 * persist per recipient; only the aggregate counters on the NotificationBatch
 * row are updated (same "never lists individual recipients" contract as the
 * user broadcast).
 */
class GuestMassNotificationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** See MassNotificationJob — long-running, never retried (a retry re-notifies). */
    public int $timeout = 1800;
    public int $tries = 1;
    public int $uniqueFor = 7200;

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

    /** Per-class lock — won't collide with MassNotificationJob for a 'both' batch. */
    public function uniqueId(): string
    {
        return (string) $this->batchId;
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

        $query->chunkById(500, function ($tokens) use ($firebase, $batch, $registeredTokens, $type) {
            $items = [];   // FirebaseService::sendBatch() items
            $meta  = [];   // parallel: index => guest token id

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
                    'type'      => $type,
                    'audience'  => 'guest',
                    'batch_id'  => (string) $batch->id,
                    'timestamp' => now()->toIso8601String(),
                ];
                if (!empty($this->content['action_url'])) {
                    $pushData['action_url'] = (string) $this->content['action_url'];
                }

                $items[] = [
                    'token' => $token->fcm_token,
                    'title' => $title,
                    'body'  => $body,
                    'data'  => $pushData,
                ];
                $meta[] = $token->id;
            }

            if (empty($items)) {
                return;
            }

            $report = $firebase->sendBatch($items);

            $sentIds = [];
            $failIds = [];
            foreach ($items as $idx => $item) {
                if ($report['results'][$item['token']] ?? false) {
                    $sentIds[] = $meta[$idx];
                } else {
                    $failIds[] = $meta[$idx];
                }
            }

            if ($sentIds) {
                GuestNotificationToken::whereIn('id', $sentIds)->update([
                    'last_notified_at' => now(),
                    'failed_attempts'  => 0,
                    'last_failed_at'   => null,
                ]);
            }
            if ($failIds) {
                GuestNotificationToken::whereIn('id', $failIds)->update([
                    'failed_attempts' => DB::raw('failed_attempts + 1'),
                    'last_failed_at'  => now(),
                ]);
            }
            if (!empty($report['invalid_tokens'])) {
                GuestNotificationToken::whereIn('fcm_token', array_unique($report['invalid_tokens']))
                    ->update(['is_active' => false]);
            }

            $batch->increment('sent_count', count($sentIds));
            $batch->increment('failed_count', count($failIds));
        }, 'id', 'id');

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

    /** Never leave the batch stuck on 'processing' if the job throws. */
    public function failed(\Throwable $e): void
    {
        $batch = NotificationBatch::find($this->batchId);

        if ($batch && in_array($batch->audience, ['guests', 'both'], true)
            && !in_array($batch->status, ['completed', 'cancelled'], true)) {
            $batch->update(['status' => 'failed']);
        }

        Log::error("GuestMassNotificationJob: batch {$this->batchId} failed: " . $e->getMessage());
    }
}
