<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderWorkingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'day_of_week',
        'is_open',
        'open_time',
        'close_time'
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_open' => 'boolean'
    ];

    // Relations
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    // Helpers
    public function getDayNameAttribute()
    {
        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday'
        ];
        return $days[$this->day_of_week] ?? '';
    }

    public function getDayNameArAttribute()
    {
        $days = [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت'
        ];
        return $days[$this->day_of_week] ?? '';
    }

    public function getLocalizedDayNameAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->day_name_ar : $this->day_name;
    }

    public function getFormattedHoursAttribute()
    {
        if (!$this->is_open) {
            return app()->getLocale() === 'ar' ? 'مغلق' : 'Closed';
        }
        return $this->open_time . ' - ' . $this->close_time;
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }

    public function scopeForDay($query, $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
}