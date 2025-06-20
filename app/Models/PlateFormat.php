<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlateFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
        'city_id',
        'is_active',
        'background_color',
        'text_color',
        'width_mm',
        'height_mm',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relation avec le pays
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Relation avec la ville
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Relation avec les champs du format
     */
    public function fields()
    {
        return $this->hasMany(PlateFormatField::class);
    }

    /**
     * Relation avec les plaques d'immatriculation
     */
    public function licensePlates()
    {
        return $this->hasMany(LicensePlate::class);
    }
}
