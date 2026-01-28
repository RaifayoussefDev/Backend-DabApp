<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstructorLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'location_name',
        'location_name_ar',
        'city_id',
        'is_available'
    ];

    protected $casts = [
        'is_available' => 'boolean'
    ];

    // Relations
    public function instructor()
    {
        return $this->belongsTo(RidingInstructor::class, 'instructor_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeInCity($query, $cityId)
    {
        return $query->where('city_id', $cityId);
    }

    // Accessors
    public function getLocalizedLocationNameAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->location_name_ar : $this->location_name;
    }

    public function getFullLocationNameAttribute()
    {
        $cityName = $this->city ? $this->city->name : '';
        return $this->localized_location_name . ($cityName ? ', ' . $cityName : '');
    }
}