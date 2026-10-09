<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotorcycleImport extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Max row-level errors kept on the record (the rest are only counted). */
    public const MAX_ROW_ERRORS = 500;

    protected $fillable = [
        'admin_id',
        'original_name',
        'file_path',
        'data_path',
        'status',
        'default_type_id',
        'total_rows',
        'processed_rows',
        'data_offset',
        'inserted_count',
        'updated_count',
        'unchanged_count',
        'skipped_count',
        'failed_count',
        'new_brands_count',
        'new_models_count',
        'column_map',
        'mapped_headers',
        'unmapped_headers',
        'row_errors',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'default_type_id'  => 'integer',
        'total_rows'       => 'integer',
        'processed_rows'   => 'integer',
        'data_offset'      => 'integer',
        'inserted_count'   => 'integer',
        'updated_count'    => 'integer',
        'unchanged_count'  => 'integer',
        'skipped_count'    => 'integer',
        'failed_count'     => 'integer',
        'new_brands_count' => 'integer',
        'new_models_count' => 'integer',
        'column_map'       => 'array',
        'mapped_headers'   => 'array',
        'unmapped_headers' => 'array',
        'row_errors'       => 'array',
        'started_at'       => 'datetime',
        'finished_at'      => 'datetime',
    ];

    protected $hidden = ['column_map', 'file_path', 'data_path'];

    protected $appends = ['progress'];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function defaultType()
    {
        return $this->belongsTo(MotorcycleType::class, 'default_type_id');
    }

    public function getProgressAttribute(): int
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return 100;
        }
        if ($this->total_rows <= 0) {
            return 0;
        }
        return (int) min(99, floor($this->processed_rows * 100 / $this->total_rows));
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PREPARING, self::STATUS_PROCESSING], true);
    }
}
