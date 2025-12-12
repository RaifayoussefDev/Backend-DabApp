<?php
// app/Models/NotificationGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NotificationGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'filters',
        'members_count',
        'last_calculated_at',
        'firebase_topic',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_active' => 'boolean',
        'last_calculated_at' => 'datetime',
    ];

    // Relations
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(NotificationCampaign::class, 'target_group_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function calculateMembers(): int
    {
        $query = User::query();

        foreach ($this->filters as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        $count = $query->count();

        $this->update([
            'members_count' => $count,
            'last_calculated_at' => now(),
        ]);

        return $count;
    }

    public function getMembers()
    {
        $query = User::query();

        foreach ($this->filters as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->get();
    }

    public function generateFirebaseTopic(): string
    {
        if (!$this->firebase_topic) {
            $topic = Str::slug($this->name, '_');
            $this->update(['firebase_topic' => $topic]);
        }

        return $this->firebase_topic;
    }

    // Accessors
    public function getNeedRecalculationAttribute(): bool
    {
        if (!$this->last_calculated_at) {
            return true;
        }

        // Recalculer si plus de 24h
        return $this->last_calculated_at->diffInHours(now()) > 24;
    }
}
