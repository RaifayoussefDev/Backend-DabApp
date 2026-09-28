<?php

namespace App\Models\Marketplace;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends MarketplaceModel
{
    protected $table = 'marketplace_vendors';

    protected $fillable = [
        'user_id', 'shop_name', 'shop_name_ar', 'slug', 'description', 'description_ar',
        'logo_path', 'cover_image_path', 'brand_color',
        'phone', 'email', 'country_id', 'city_id',
        'status', 'status_reason', 'approved_at', 'approved_by', 'is_featured',
        'commission_override', 'bank_name', 'iban',
        'rating_avg', 'reviews_count',
    ];

    protected $casts = [
        'commission_override' => 'decimal:2',
        'rating_avg'          => 'decimal:2',
        'reviews_count'       => 'integer',
        'approved_at'         => 'datetime',
        'iban'                => 'encrypted',
        'is_featured'         => 'boolean',
    ];

    protected $hidden = ['iban', 'bank_name', 'commission_override'];

    protected $appends = ['logo_url', 'cover_image_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->storageUrl($this->logo_path);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->storageUrl($this->cover_image_path);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(VendorShippingMethod::class, 'vendor_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'vendor_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(VendorPayout::class, 'vendor_id');
    }
}
