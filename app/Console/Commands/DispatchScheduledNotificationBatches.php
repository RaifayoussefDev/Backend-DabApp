<?php

namespace App\Console\Commands;

use App\Jobs\GuestMassNotificationJob;
use App\Jobs\MassNotificationJob;
use App\Models\GuestNotificationToken;
use App\Models\NotificationBatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchScheduledNotificationBatches extends Command
{
    protected $signature = 'notifications:dispatch-scheduled';

    protected $description = 'Dispatch admin broadcast notifications whose scheduled send time has arrived';

    public function handle(): int
    {
        $due = NotificationBatch::due()->get();

        foreach ($due as $batch) {
            $audience = $batch->audience ?? 'users';
            $targetsUsers = in_array($audience, ['users', 'both'], true);
            $targetsGuests = in_array($audience, ['guests', 'both'], true);

            // Re-count against current filters — the audience may have changed since scheduling.
            $totalTargeted = 0;
            if ($targetsUsers) {
                $totalTargeted += User::query()->applyFilters($batch->filters ?? [])->count();
            }
            if ($targetsGuests) {
                $totalTargeted += GuestNotificationToken::query()->active()
                    ->matchingFilters($batch->guest_filters ?? [])->count();
            }

            $batch->update([
                'status' => 'pending',
                'total_targeted' => $totalTargeted,
            ]);

            $content = [
                'title_en' => $batch->title_en,
                'title_ar' => $batch->title_ar,
                'body_en' => $batch->body_en,
                'body_ar' => $batch->body_ar,
                'action_url' => $batch->action_url,
                'type' => $batch->type,
            ];

            if ($targetsUsers) {
                MassNotificationJob::dispatch(
                    $batch->id,
                    $batch->filters ?? [],
                    $content,
                    $batch->channels ?? ['push'],
                    $batch->created_by
                );
            }
            if ($targetsGuests) {
                GuestMassNotificationJob::dispatch($batch->id, $batch->guest_filters ?? [], $content);
            }

            Log::info("notifications:dispatch-scheduled: dispatched batch {$batch->id}", [
                'audience' => $audience,
                'total_targeted' => $totalTargeted,
            ]);
        }

        $this->info("Dispatched {$due->count()} scheduled broadcast(s).");

        return Command::SUCCESS;
    }
}
