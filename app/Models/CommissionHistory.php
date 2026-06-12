<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_setting_id',
        'changed_by',
        'old_percentage',
        'new_percentage',
        'reason',
        'changed_at',
    ];

    protected $casts = [
        'old_percentage' => 'decimal:2',
        'new_percentage' => 'decimal:2',
        'changed_at'     => 'datetime',
    ];

    public function setting()
    {
        return $this->belongsTo(CommissionSetting::class, 'commission_setting_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
