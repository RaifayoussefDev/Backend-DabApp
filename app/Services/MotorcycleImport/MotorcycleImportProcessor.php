<?php

namespace App\Services\MotorcycleImport;

use Illuminate\Support\Facades\DB;

/**
 * Upserts spreadsheet rows into brand -> model -> year -> detail.
 *
 * Existing rows are matched by natural key (brand name, brand+model name,
 * model+year, year_id) case-insensitively and are NEVER deleted or re-keyed:
 * their ids are referenced by listings (motorcycles), garages, pricing rules...
 * Only motorcycle_details columns are updated, and only when the new value is
 * non-empty and different from what is stored.
 */
class MotorcycleImportProcessor
{
    /** @var array<string,int> lower(name) => id */
    private array $brands = [];
    /** @var array<string,int> "brandId|lower(name)" => id */
    private array $models = [];
    /** @var array<string,int> "modelId|year" => id */
    private array $years = [];
    /** @var array<string,int> lower(name) => id */
    private array $types = [];

    private array $detailColumns;

    public array $counters = [
        'inserted_count'   => 0,
        'updated_count'    => 0,
        'unchanged_count'  => 0,
        'skipped_count'    => 0,
        'failed_count'     => 0,
        'new_brands_count' => 0,
        'new_models_count' => 0,
    ];

    /** @var array<int, array{row:int, message:string}> */
    public array $errors = [];

    public function __construct(private array $columnMap, private ?int $defaultTypeId)
    {
        $this->detailColumns = MotorcycleImportMapper::detailColumns();
        $this->loadCaches();
    }

    private function loadCaches(): void
    {
        // Ordered by id DESC so that, if duplicates exist, the oldest id wins.
        foreach (DB::table('motorcycle_brands')->orderByDesc('id')->get(['id', 'name']) as $b) {
            $this->brands[$this->key($b->name)] = $b->id;
        }
        foreach (DB::table('motorcycle_models')->orderByDesc('id')->get(['id', 'brand_id', 'name']) as $m) {
            $this->models[$m->brand_id . '|' . $this->key($m->name)] = $m->id;
        }
        foreach (DB::table('motorcycle_years')->orderByDesc('id')->get(['id', 'model_id', 'year']) as $y) {
            $this->years[$y->model_id . '|' . $y->year] = $y->id;
        }
        foreach (DB::table('motorcycle_types')->get(['id', 'name']) as $t) {
            $this->types[$this->key($t->name)] = $t->id;
        }
    }

    /**
     * @param array<int, array{0:int, 1:array}> $rows [excelRowNumber, cells]
     */
    public function processBatch(array $rows): void
    {
        // Pass 1: resolve (or create) brand / model / year ids.
        $resolved = []; // [rowNumber, yearId, details]
        foreach ($rows as [$rowNumber, $cells]) {
            try {
                $data = MotorcycleImportMapper::mapRow($cells, $this->columnMap);

                if ($data['make'] === '' || $data['model'] === '') {
                    $this->counters['skipped_count']++;
                    continue;
                }

                $year = MotorcycleImportMapper::parseYear($data['year']);
                if ($year === null) {
                    $this->fail($rowNumber, "Invalid year \"{$data['year']}\" for {$data['make']} {$data['model']}");
                    continue;
                }

                $brandId = $this->brandId($data['make']);
                $modelId = $this->modelId($brandId, $data['model'], $data['category']);
                $yearId = $this->yearId($modelId, $year);

                $resolved[] = [$rowNumber, $yearId, $data['details']];
            } catch (\Throwable $e) {
                $this->fail($rowNumber, $e->getMessage());
            }
        }

        if (!$resolved) {
            return;
        }

        // Pass 2: one query for the existing details of the whole batch.
        $yearIds = array_values(array_unique(array_column($resolved, 1)));
        $existing = [];
        foreach (DB::table('motorcycle_details')->whereIn('year_id', $yearIds)->orderByDesc('id')->get() as $d) {
            $existing[$d->year_id] = (array) $d; // oldest id wins on duplicates
        }

        $now = now();
        $inserts = []; // year_id => row (keyed so duplicate rows in the file merge)

        foreach ($resolved as [$rowNumber, $yearId, $details]) {
            try {
                if (isset($inserts[$yearId])) {
                    $inserts[$yearId] = array_merge($inserts[$yearId], $details);
                    $this->counters['unchanged_count']++;
                    continue;
                }

                if (!isset($existing[$yearId])) {
                    $inserts[$yearId] = $details;
                    $this->counters['inserted_count']++;
                    continue;
                }

                $current = $existing[$yearId];
                $changes = $this->diff($current, $details);

                if ($changes) {
                    $changes['updated_at'] = $now;
                    DB::table('motorcycle_details')->where('id', $current['id'])->update($changes);
                    $existing[$yearId] = array_merge($current, $changes);
                    $this->counters['updated_count']++;
                } else {
                    $this->counters['unchanged_count']++;
                }
            } catch (\Throwable $e) {
                $this->fail($rowNumber, $e->getMessage());
            }
        }

        if ($inserts) {
            $this->bulkInsertDetails($inserts, $now);
        }
    }

    /** Insert rows share one column set so a single multi-row INSERT works. */
    private function bulkInsertDetails(array $inserts, $now): void
    {
        $columns = [];
        foreach ($inserts as $details) {
            $columns += array_flip(array_keys($details));
        }
        $columns = array_keys($columns);

        $payload = [];
        foreach ($inserts as $yearId => $details) {
            $row = ['year_id' => $yearId, 'created_at' => $now, 'updated_at' => $now];
            foreach ($columns as $c) {
                $row[$c] = $details[$c] ?? null;
            }
            $payload[] = $row;
        }

        foreach (array_chunk($payload, 100) as $chunk) {
            DB::table('motorcycle_details')->insert($chunk);
        }
    }

    /** Columns whose new non-empty value differs from the stored one. */
    private function diff(array $current, array $details): array
    {
        $changes = [];
        foreach ($details as $column => $value) {
            if (!array_key_exists($column, $current)) {
                continue; // column not migrated yet on this DB
            }
            $old = $current[$column];
            $type = $this->detailColumns[$column] ?? 'text';

            if ($type === 'float' || $type === 'int') {
                if ($old === null || abs((float) $old - (float) $value) > 0.0001) {
                    $changes[$column] = $value;
                }
            } elseif ($old === null || trim((string) $old) !== trim((string) $value)) {
                $changes[$column] = $value;
            }
        }
        return $changes;
    }

    private function brandId(string $name): int
    {
        $key = $this->key($name);
        if (!isset($this->brands[$key])) {
            // Re-check in DB: the unique index + ci collation is the source of truth.
            $id = DB::table('motorcycle_brands')->where('name', $name)->value('id');
            if (!$id) {
                $id = DB::table('motorcycle_brands')->insertGetId([
                    'name'       => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->counters['new_brands_count']++;
            }
            $this->brands[$key] = $id;
        }
        return $this->brands[$key];
    }

    private function modelId(int $brandId, string $name, string $category): int
    {
        $key = $brandId . '|' . $this->key($name);
        if (!isset($this->models[$key])) {
            $id = DB::table('motorcycle_models')->where('brand_id', $brandId)->where('name', $name)->orderBy('id')->value('id');
            if ($id) {
                return $this->models[$key] = $id;
            }

            $typeId =($category !== '' ? ($this->types[$this->key($category)] ?? null) : null) ?? $this->defaultTypeId;
            if (!$typeId) {
                throw new \RuntimeException("New model \"{$name}\" needs a type: choose a default type for new models.");
            }
            $this->models[$key] = DB::table('motorcycle_models')->insertGetId([
                'brand_id'   => $brandId,
                'name'       => $name,
                'type_id'    => $typeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->counters['new_models_count']++;
        }
        return $this->models[$key];
    }

    private function yearId(int $modelId, int $year): int
    {
        $key = $modelId . '|' . $year;
        if (!isset($this->years[$key])) {
            $this->years[$key] = DB::table('motorcycle_years')->insertGetId([
                'model_id'   => $modelId,
                'year'       => $year,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $this->years[$key];
    }

    private function fail(int $rowNumber, string $message): void
    {
        $this->counters['failed_count']++;
        $this->errors[] = ['row' => $rowNumber, 'message' => mb_substr($message, 0, 300)];
    }

    private function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }
}
