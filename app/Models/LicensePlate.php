<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicensePlate extends Model
{
    protected $fillable = [
        'listing_id',
        'characters',
        'country_id',
        'type_id',
        'color_id',
        'digits_count',
        'first_letter',
        'second_letter',
        'third_letter',
        'numbers',
        'city_id',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PlateType::class, 'type_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(PlateColor::class, 'color_id');
    }
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

}
