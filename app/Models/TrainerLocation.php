<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'location_name',
        'location_name_ar',
        'city_id',
        'latitude',
        'longitude',
        'is_available',
        'price_per_hour',
        'price_per_mission',
    ];

    protected $casts = [
        'latitude'          => 'decimal:7',
        'longitude'         => 'decimal:7',
        'is_available'      => 'boolean',
        'price_per_hour'    => 'decimal:2',
        'price_per_mission' => 'decimal:2',
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ar'
            ? ($this->location_name_ar ?? $this->location_name)
            : $this->location_name;
    }
}
