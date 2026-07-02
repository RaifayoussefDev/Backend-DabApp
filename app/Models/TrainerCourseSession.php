<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainerCourseSession extends Model
{
    protected $fillable = [
        'course_id',
        'session_number',
        'title',
        'title_ar',
        'description',
        'description_ar',
        'duration_hours',
    ];

    protected $casts = [
        'session_number' => 'integer',
        'duration_hours' => 'integer',
    ];

    protected $appends = ['localized_title', 'localized_description'];

    public function course()
    {
        return $this->belongsTo(TrainerCourse::class, 'course_id');
    }

    public function getLocalizedTitleAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? ($this->title_ar ?? $this->title) : $this->title;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description;
    }
}
