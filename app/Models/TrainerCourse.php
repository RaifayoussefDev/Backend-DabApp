<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'level_id',
        'title',
        'title_ar',
        'description',
        'description_ar',
        'hours_per_session',
        'total_sessions',
        'session_date',
        'session_time',
        'original_price',
        'promo_price',
        'location_id',
        'can_travel',
        'price_per_km',
        'status',
        'is_active',
    ];

    protected $casts = [
        'hours_per_session' => 'integer',
        'total_sessions'    => 'integer',
        'session_date'      => 'date',
        'original_price'    => 'decimal:2',
        'promo_price'       => 'decimal:2',
        'can_travel'    => 'boolean',
        'price_per_km'  => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    protected $appends = ['effective_price', 'total_price', 'localized_title', 'localized_description'];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function level()
    {
        return $this->belongsTo(TrainerLevel::class, 'level_id');
    }

    public function location()
    {
        return $this->belongsTo(TrainerLocation::class, 'location_id');
    }

    public function sessions()
    {
        return $this->hasMany(TrainerCourseSession::class, 'course_id')->orderBy('session_number');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('is_active', true);
    }

    /** Returns promo price if set, otherwise original price. */
    public function getEffectivePriceAttribute(): string
    {
        return $this->promo_price ?? $this->original_price;
    }

    /** effective_price × hours_per_session × total_sessions */
    public function getTotalPriceAttribute(): string
    {
        return number_format(
            (float) $this->effective_price * $this->hours_per_session * $this->total_sessions,
            2,
            '.',
            ''
        );
    }

    public function getLocalizedTitleAttribute(): string
    {
        return app()->getLocale() === 'ar' ? ($this->title_ar ?? $this->title) : $this->title;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description;
    }
}
