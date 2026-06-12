<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'commission_percentage',
        'is_active',
        'effective_from',
        'effective_until',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'commission_percentage' => 'decimal:2',
        'is_active'             => 'boolean',
        'effective_from'        => 'date',
        'effective_until'       => 'date',
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class, 'entity_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function history()
    {
        return $this->hasMany(CommissionHistory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', now()->toDateString()));
    }

    public function scopeGlobal($query)
    {
        return $query->where('entity_type', 'global');
    }

    public function scopeForTrainer($query, int $trainerId)
    {
        return $query->where('entity_type', 'trainer')->where('entity_id', $trainerId);
    }
}
