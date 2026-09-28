<?php

namespace App\Models\Marketplace;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends MarketplaceModel
{
    protected $table = 'marketplace_addresses';

    protected $fillable = [
        'user_id', 'full_name', 'phone', 'country_id', 'city_id',
        'address_line', 'address_line_2', 'postal_code', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
