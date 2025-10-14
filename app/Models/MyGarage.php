<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyGarage extends Model
{
    use HasFactory;

    protected $table = 'my_garage';

    protected $fillable = [
        'user_id',
        'brand_id',
        'model_id',
        'year_id',
        'type_id',
        'title',
        'description',
        'picture',
        'is_default',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_default' => 'boolean',
    ];


    /**
     * Get the user that owns the garage entry
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the motorcycle brand
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(MotorcycleBrand::class, 'brand_id');
    }

    /**
     * Get the motorcycle model
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(MotorcycleModel::class, 'model_id');
    }

    /**
     * Get the motorcycle year
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(MotorcycleYear::class, 'year_id');
    }

    /**
     * Get the motorcycle type
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MotorcycleType::class, 'type_id');
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get full motorcycle info as string
     */
    public function getFullMotorcycleNameAttribute(): string
    {
        return "{$this->brand->name} {$this->model->name} ({$this->year->year})";
    }
}
