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
        'price_type',
        'title',
        'title_ar',
        'description',
        'description_ar',
        'image',
        'hours_per_session',
        'total_sessions',
        'start_date',
        'session_time',
        'original_price',
        'promo_price',
        'location_id',
        'can_travel',
        'price_per_km',
        'status',
        'draft_reason',
        'is_active',
    ];

    protected $casts = [
        'hours_per_session' => 'integer',
        'total_sessions'    => 'integer',
        'start_date'        => 'date',
        'original_price'    => 'decimal:2',
        'promo_price'       => 'decimal:2',
        'can_travel'    => 'boolean',
        'price_per_km'  => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    protected $appends = ['image_url', 'effective_price', 'price_per_hour', 'price_per_session', 'total_price', 'localized_title', 'localized_description'];

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

    public function equipment()
    {
        return $this->belongsToMany(TrainerEquipment::class, 'trainer_course_equipment', 'course_id', 'trainer_equipment_id');
    }

    public function trainingBikes()
    {
        return $this->belongsToMany(TrainerTrainingBike::class, 'trainer_course_training_bikes', 'course_id', 'trainer_training_bike_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('is_active', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : null;
    }

    /** Returns promo price if set, otherwise original price. */
    public function getEffectivePriceAttribute(): string
    {
        return $this->promo_price ?? $this->original_price;
    }

    /** Only meaningful when price_type is 'per_hour' — null for flat 'total'-priced courses. */
    public function getPricePerHourAttribute(): ?string
    {
        return $this->price_type === 'per_hour' ? $this->effective_price : null;
    }

    /** price_per_hour × hours_per_session — null for flat 'total'-priced courses. */
    public function getPricePerSessionAttribute(): ?string
    {
        if ($this->price_type !== 'per_hour') {
            return null;
        }

        return number_format((float) $this->effective_price * $this->hours_per_session, 2, '.', '');
    }

    /**
     * 'per_hour': effective_price × hours_per_session × total_sessions.
     * 'total': effective_price already is the whole package price.
     */
    public function getTotalPriceAttribute(): string
    {
        if ($this->price_type === 'total') {
            return number_format((float) $this->effective_price, 2, '.', '');
        }

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
