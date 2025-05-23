<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'max_uses',
        'used_count',
        'status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function isValid()
    {
        return $this->status === 'active' && $this->used_count < $this->max_uses && now()->between($this->start_date, $this->end_date);
    }
}
