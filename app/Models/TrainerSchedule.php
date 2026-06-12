<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected $casts = [
        'day_of_week'  => 'integer',
        'is_available' => 'boolean',
    ];

    protected $appends = ['day_name', 'day_name_ar'];

    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    private const DAY_NAMES_AR = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::DAY_NAMES[$this->day_of_week] ?? '';
    }

    public function getDayNameArAttribute(): string
    {
        return self::DAY_NAMES_AR[$this->day_of_week] ?? '';
    }
}
