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

    public function course()
    {
        return $this->belongsTo(TrainerCourse::class, 'course_id');
    }
}
