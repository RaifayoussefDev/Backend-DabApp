<?php

namespace App\Jobs;

use App\Models\MotorcycleImport;
use App\Services\MotorcycleImport\MotorcycleImportProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Step 2 of a motorcycle import: processes rows from the checkpoint for at most
 * TIME_BUDGET seconds, then dispatches the next chunk.
 *
 * Every chunk is short (< worker --timeout=120 and < queue retry_after=90), so a
 * 43k-row file never times out and a chunk is never re-reserved while running.
 * Each batch commits its data AND the checkpoint in the same transaction, so a
 * crash/retry resumes exactly where the last commit stopped.
 */
class ProcessMotorcycleImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TIME_BUDGET = 40;
    public const BATCH_SIZE = 250;

    public int $timeout = 110;
    public int $tries = 3;
    public array $backoff = [10, 60];

    /**
     * @param int $expectedProcessed processed_rows when this chunk was dispatched;
     *                               a mismatch means a stale/duplicate job → no-op.
     */
    public function __construct(public int $importId, public int $expectedProcessed)
    {
    }

    public function handle(): void
    {
        $import = MotorcycleImport::find($this->importId);
        if (!$import
            || $import->status !== MotorcycleImport::STATUS_PROCESSING
            || $import->processed_rows !== $this->expectedProcessed) {
            return;
        }

        ini_set('memory_limit', '512M');

        $fh = fopen(Storage::disk('local')->path($import->data_path), 'rb');
        fseek($fh, (int) $import->data_offset);

        $columnMap = [];
        foreach ($import->column_map as $index => $definition) {
            $columnMap[(int) $index] = $definition;
        }

        $processor = new MotorcycleImportProcessor($columnMap, $import->default_type_id);
        $deadline = microtime(true) + self::TIME_BUDGET;
        $processed = $import->processed_rows;

        try {
            while (true) {
                $rows = [];
                while (count($rows) < self::BATCH_SIZE && ($line = fgetcsv($fh, null, ',', '"', '')) !== false) {
                    if ($line === [null]) {
                        continue;
                    }
                    $rowNumber = (int) $line[0];
                    $rows[] = [$rowNumber, $line];
                }

                if (!$rows) {
                    $this->complete();
                    return;
                }

                $offset = ftell($fh);
                $stop = false;

                DB::transaction(function () use ($processor, $rows, $offset, &$processed, &$stop) {
                    $fresh = MotorcycleImport::whereKey($this->importId)->lockForUpdate()->first();
                    if (!$fresh || $fresh->status !== MotorcycleImport::STATUS_PROCESSING) {
                        $stop = true; // cancelled from the admin panel
                        return;
                    }

                    $processor->processBatch($rows);
                    $processed += count($rows);

                    $updates = [
                        'processed_rows' => $processed,
                        'data_offset'    => $offset,
                    ];
                    foreach ($processor->counters as $column => $count) {
                        $updates[$column] = $fresh->{$column} + $count;
                        $processor->counters[$column] = 0;
                    }
                    if ($processor->errors) {
                        $errors = array_merge($fresh->row_errors ?? [], $processor->errors);
                        $updates['row_errors'] = array_slice($errors, 0, MotorcycleImport::MAX_ROW_ERRORS);
                        $processor->errors = [];
                    }

                    $fresh->update($updates);
                });

                if ($stop) {
                    return;
                }

                if (count($rows) < self::BATCH_SIZE && feof($fh)) {
                    $this->complete();
                    return;
                }

                if (microtime(true) >= $deadline) {
                    self::dispatch($this->importId, $processed);
                    return;
                }
            }
        } finally {
            fclose($fh);
        }
    }

    private function complete(): void
    {
        $import = MotorcycleImport::find($this->importId);
        if (!$import || $import->status !== MotorcycleImport::STATUS_PROCESSING) {
            return;
        }

        $import->update([
            'status'      => MotorcycleImport::STATUS_COMPLETED,
            'finished_at' => now(),
        ]);

        if ($import->data_path) {
            Storage::disk('local')->delete($import->data_path);
        }
    }

    public function failed(\Throwable $e): void
    {
        $import = MotorcycleImport::find($this->importId);
        if ($import && $import->status === MotorcycleImport::STATUS_PROCESSING) {
            $import->update([
                'status'        => MotorcycleImport::STATUS_FAILED,
                'error_message' => 'Stopped at row ' . $import->processed_rows . ': ' . mb_substr($e->getMessage(), 0, 1000),
            ]);
        }
    }
}
