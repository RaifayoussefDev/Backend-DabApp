<?php

namespace App\Jobs;

use App\Models\MotorcycleImport;
use App\Services\MotorcycleImport\MotorcycleImportMapper;
use App\Services\MotorcycleImport\XlsxStreamReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Step 1 of a motorcycle import: streams the uploaded .xlsx/.csv once, locates
 * the header row, maps columns by name and writes the data rows to a compact
 * CSV that the chunk jobs can seek into by byte offset.
 *
 * Streaming keeps this at ~20s / ~110MB for 43k rows x 100 columns, so it stays
 * well under the worker's --timeout and the queue's retry_after.
 */
class PrepareMotorcycleImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /** Never retry: a second attempt would race the first on the same files. Recovery = "Resume". */
    public int $tries = 1;

    /** The header row is searched within the first N rows (the sheet has a group row above it). */
    private const HEADER_SEARCH_ROWS = 20;

    public function __construct(public int $importId)
    {
    }

    public function handle(): void
    {
        $import = MotorcycleImport::find($this->importId);
        if (!$import || $import->status !== MotorcycleImport::STATUS_QUEUED) {
            return;
        }

        ini_set('memory_limit', '1024M');

        $import->update([
            'status'     => MotorcycleImport::STATUS_PREPARING,
            'started_at' => $import->started_at ?? now(),
        ]);

        $disk = Storage::disk('local');
        $source = $disk->path($import->file_path);
        $dataPath = dirname($import->file_path) . '/data.csv';

        $out = fopen($disk->path($dataPath), 'wb');
        $columnMap = null;
        $headerInfo = null;
        $total = 0;

        try {
            foreach ($this->readRows($source) as [$rowNumber, $cells]) {
                if ($columnMap === null) {
                    if (MotorcycleImportMapper::isHeaderRow($cells)) {
                        $headerInfo = MotorcycleImportMapper::buildColumnMap($cells);
                        $columnMap = $headerInfo['map'];
                    } elseif ($rowNumber > self::HEADER_SEARCH_ROWS) {
                        break;
                    }
                    continue;
                }

                if (!$this->hasData($cells) || MotorcycleImportMapper::isSummaryRow($cells, $columnMap)) {
                    continue;
                }

                // Only the mapped columns are kept; the row number travels first for error reporting.
                $line = [$rowNumber];
                foreach ($columnMap as $index => $_) {
                    $line[] = $cells[$index] ?? '';
                }
                fputcsv($out, $line, ',', '"', '');
                $total++;
            }
        } finally {
            fclose($out);
        }

        if ($columnMap === null) {
            $disk->delete($dataPath);
            $this->markFailed($import, 'Header row not found: the first ' . self::HEADER_SEARCH_ROWS . ' rows must contain "Make", "Model" and "Year" columns.');
            return;
        }

        $keys = array_column($columnMap, 0);
        if (!in_array(MotorcycleImportMapper::KEY_YEAR, $keys, true)) {
            $disk->delete($dataPath);
            $this->markFailed($import, 'The header row has no "Year" column.');
            return;
        }

        // Re-index the map on the compact CSV positions (0 = row number).
        $compactMap = [];
        $position = 1;
        foreach ($columnMap as $definition) {
            $compactMap[$position++] = $definition;
        }

        $import->refresh();
        if ($import->status === MotorcycleImport::STATUS_CANCELLED) {
            $disk->delete($dataPath);
            return;
        }

        $import->update([
            'status'           => MotorcycleImport::STATUS_PROCESSING,
            'data_path'        => $dataPath,
            'total_rows'       => $total,
            'processed_rows'   => 0,
            'data_offset'      => 0,
            'inserted_count'   => 0,
            'updated_count'    => 0,
            'unchanged_count'  => 0,
            'skipped_count'    => 0,
            'failed_count'     => 0,
            'new_brands_count' => 0,
            'new_models_count' => 0,
            'row_errors'       => null,
            'column_map'       => $compactMap,
            'mapped_headers'   => $headerInfo['mapped'],
            'unmapped_headers' => $headerInfo['unmapped'],
        ]);

        ProcessMotorcycleImportChunkJob::dispatch($import->id, 0);
    }

    /** @return \Generator<int, array{0:int, 1:array}> */
    private function readRows(string $path): \Generator
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'xlsx') {
            yield from (new XlsxStreamReader($path))->rows();
            return;
        }

        // CSV / TXT
        $fh = fopen($path, 'rb');
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        $first = fgets($fh);
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        fseek($fh, $bom === "\xEF\xBB\xBF" ? 3 : 0);

        $rowNumber = 0;
        while (($cells = fgetcsv($fh, null, $delimiter, '"', '')) !== false) {
            $rowNumber++;
            if ($cells === [null]) {
                continue;
            }
            yield [$rowNumber, $cells];
        }
        fclose($fh);
    }

    private function hasData(array $cells): bool
    {
        foreach ($cells as $c) {
            if (trim((string) $c) !== '') {
                return true;
            }
        }
        return false;
    }

    private function markFailed(MotorcycleImport $import, string $message): void
    {
        $import->update([
            'status'        => MotorcycleImport::STATUS_FAILED,
            'error_message' => $message,
            'finished_at'   => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $import = MotorcycleImport::find($this->importId);
        if ($import && $import->isRunning()) {
            $this->markFailed($import, 'Preparation failed: ' . mb_substr($e->getMessage(), 0, 1000));
        }
    }
}
