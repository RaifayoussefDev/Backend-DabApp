<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerLevelApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'level_id',
        'proposed_price',
        'approved_price',
        'status',
        'notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'proposed_price' => 'decimal:2',
        'approved_price' => 'decimal:2',
        'approved_at'    => 'datetime',
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function level()
    {
        return $this->belongsTo(TrainerLevel::class, 'level_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
