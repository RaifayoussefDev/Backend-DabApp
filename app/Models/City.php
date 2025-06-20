<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
    ];

    // Define the relationship with Country
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    // Optional: If listings reference the city
    public function listings()
    {
        return $this->hasMany(Listing::class);
    }

    public function plateFormats()
    {
        return $this->hasMany(PlateFormat::class);
    }

    /**
     * Relation avec les formats de plaques actifs uniquement
     */
    public function activePlateFormats()
    {
        return $this->hasMany(PlateFormat::class)->where('is_active', true);
    }
}
