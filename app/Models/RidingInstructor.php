<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RidingInstructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'instructor_name',
        'instructor_name_ar',
        'bio',
        'bio_ar',
        'photo',
        'certifications',
        'experience_years',
        'rating_average',
        'total_sessions',
        'is_available'
    ];

    protected $casts = [
        'certifications' => 'array',
        'experience_years' => 'integer',
        'rating_average' => 'decimal:2',
        'total_sessions' => 'integer',
        'is_available' => 'boolean'
    ];

    // Relations
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function locations()
    {
        return $this->hasMany(InstructorLocation::class, 'instructor_id');
    }

    public function availableLocations()
    {
        return $this->locations()->where('is_available', true);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeTopRated($query, $minRating = 4.0)
    {
        return $query->where('rating_average', '>=', $minRating);
    }

    public function scopeExperienced($query, $minYears = 5)
    {
        return $query->where('experience_years', '>=', $minYears);
    }

    // Helper Methods
    public function incrementTotalSessions()
    {
        $this->increment('total_sessions');
    }

    public function updateRating($newRating)
    {
        // TODO: Implement rating calculation logic
        // This should be calculated from actual reviews
    }

    // Accessors
    public function getLocalizedInstructorNameAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->instructor_name_ar : $this->instructor_name;
    }

    public function getLocalizedBioAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->bio_ar : $this->bio;
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? asset('storage/' . $this->photo) : asset('images/default-instructor.png');
    }

    public function getExperienceLabelAttribute()
    {
        $years = $this->experience_years;
        $locale = app()->getLocale();
        
        if ($years === 1) {
            return '1 ' . ($locale === 'ar' ? 'سنة' : 'year');
        }
        
        return $years . ' ' . ($locale === 'ar' ? 'سنوات' : 'years');
    }

    public function getRatingStarsAttribute()
    {
        $fullStars = floor($this->rating_average);
        $halfStar = ($this->rating_average - $fullStars) >= 0.5 ? 1 : 0;
        $emptyStars = 5 - $fullStars - $halfStar;
        
        return str_repeat('⭐', $fullStars) . 
               str_repeat('✨', $halfStar) . 
               str_repeat('☆', $emptyStars);
    }

    public function getAvailabilityLabelAttribute()
    {
        if ($this->is_available) {
            return app()->getLocale() === 'ar' ? 'متاح' : 'Available';
        }
        return app()->getLocale() === 'ar' ? 'غير متاح' : 'Unavailable';
    }
}