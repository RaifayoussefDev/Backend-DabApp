<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorShippingMethod extends MarketplaceModel
{
    protected $table = 'marketplace_vendor_shipping_methods';

    protected $fillable = [
        'vendor_id', 'provider_id', 'uses_own_api', 'credentials',
        'flat_rate', 'estimated_days_min', 'estimated_days_max', 'is_active',
    ];

    protected $casts = [
        'uses_own_api'       => 'boolean',
        'credentials'        => 'encrypted:array',
        'flat_rate'          => 'decimal:2',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
        'is_active'          => 'boolean',
    ];

    /** Carrier API keys must never leave the server. */
    protected $hidden = ['credentials'];

    /** The API only ever says whether keys are stored, never what they are. */
    protected $appends = ['has_credentials'];

    public function getHasCredentialsAttribute(): bool
    {
        return ! empty($this->attributes['credentials']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'provider_id');
    }
}
