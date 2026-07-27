<?php

namespace App\Console\Commands;

use App\Jobs\MassNotificationJob;
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
            // Re-count against current filters — the audience may have changed since scheduling.
            $totalTargeted = User::query()->applyFilters($batch->filters ?? [])->count();

            $batch->update([
                'status' => 'pending',
                'total_targeted' => $totalTargeted,
            ]);

            MassNotificationJob::dispatch(
                $batch->id,
                $batch->filters ?? [],
                [
                    'title_en' => $batch->title_en,
                    'title_ar' => $batch->title_ar,
                    'body_en' => $batch->body_en,
                    'body_ar' => $batch->body_ar,
                    'type' => $batch->type,
                ],
                $batch->channels ?? ['push'],
                $batch->created_by
            );

            Log::info("notifications:dispatch-scheduled: dispatched batch {$batch->id}", ['total_targeted' => $totalTargeted]);
        }

        $this->info("Dispatched {$due->count()} scheduled broadcast(s).");

        return Command::SUCCESS;
    }
}
