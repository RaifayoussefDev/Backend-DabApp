<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_discount',
        'min_listing_price',
        'usage_limit',
        'per_user_limit',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'discount_value' => 'float',
        'max_discount' => 'float',
        'min_listing_price' => 'float',
    ];

    public function isValid()
    {
        return $this->status === 'active' && $this->used_count < $this->max_uses && now()->between($this->start_date, $this->end_date);
    }
}
