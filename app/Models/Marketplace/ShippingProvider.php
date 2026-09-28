<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingProvider extends MarketplaceModel
{
    protected $table = 'marketplace_shipping_providers';

    protected $fillable = ['name', 'code', 'requires_api_key', 'is_active'];

    protected $casts = [
        'requires_api_key' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function vendorMethods(): HasMany
    {
        return $this->hasMany(VendorShippingMethod::class, 'provider_id');
    }
}
