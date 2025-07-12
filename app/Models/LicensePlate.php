<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicensePlate extends Model
{
    protected $fillable = [
        'listing_id',
        'country_id',
        'type_id',
        'color_id',
        'city_id',
        'plate_format_id',
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

    public function format(): BelongsTo
    {
        return $this->belongsTo(PlateFormat::class, 'plate_format_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(LicensePlateValue::class);
    }

    public function LicensePlateValue()
    {
        return $this->hasMany(LicensePlateValue::class, 'license_plate_id'); // Ajuste les noms si nécessaire
    }
}
