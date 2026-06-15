<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trainer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'name_ar',
        'bio',
        'bio_ar',
        'photo',
        'cover',
        'specialty',
        'certifications',
        'certification_files',
        'experience_years',
        'price_per_hour',
        'rating_average',
        'total_sessions',
        'likes_count',
        'is_available',
        'status',
    ];

    protected $casts = [
        'certification_files'   => 'array',
        'experience_years'      => 'integer',
        'price_per_hour'   => 'decimal:2',
        'rating_average'   => 'decimal:2',
        'total_sessions'   => 'integer',
        'likes_count'      => 'integer',
        'is_available'     => 'boolean',
    ];

    protected $appends = ['photo_url', 'cover_url', 'localized_name', 'localized_bio', 'certification_files_urls'];

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function locations()
    {
        return $this->hasMany(TrainerLocation::class);
    }

    public function availableLocations()
    {
        return $this->locations()->where('is_available', true);
    }

    public function schedules()
    {
        return $this->hasMany(TrainerSchedule::class)->orderBy('day_of_week');
    }

    public function bookings()
    {
        return $this->hasMany(TrainerBooking::class);
    }

    public function reviews()
    {
        return $this->hasMany(TrainerReview::class)->where('is_approved', true);
    }

    public function comments()
    {
        return $this->hasMany(TrainerComment::class)->whereNull('parent_id')->where('is_approved', true);
    }

    public function favorites()
    {
        return $this->hasMany(TrainerFavorite::class);
    }

    public function likes()
    {
        return $this->hasMany(TrainerLike::class);
    }

    public function commissionSetting()
    {
        return $this->hasOne(CommissionSetting::class, 'entity_id')
            ->where('entity_type', 'trainer')
            ->where('is_active', true);
    }

    public function payouts()
    {
        return $this->hasMany(TrainerPayout::class);
    }

    public function paymentSplits()
    {
        return $this->hasMany(PaymentSplit::class);
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeTopRated($query, float $min = 4.0)
    {
        return $query->where('rating_average', '>=', $min);
    }

    public function scopeBySpecialty($query, string $specialty)
    {
        return $query->where('specialty', $specialty);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    public function incrementTotalSessions(): void
    {
        $this->increment('total_sessions');
    }

    public function recalculateRating(): void
    {
        $avg = $this->reviews()->avg('rating');
        $this->update(['rating_average' => round($avg ?? 0, 2)]);
    }

    public function getEffectiveCommissionPercentage(): float
    {
        // Trainer-specific override first, then global fallback
        $specific = CommissionSetting::where('entity_type', 'trainer')
            ->where('entity_id', $this->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', now()->toDateString()))
            ->first();

        if ($specific) {
            return (float) $specific->commission_percentage;
        }

        $global = CommissionSetting::where('entity_type', 'global')
            ->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', now()->toDateString()))
            ->first();

        return $global ? (float) $global->commission_percentage : 0.0;
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo
            ? asset('storage/' . $this->photo)
            : asset('images/default-trainer.png');
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover
            ? asset('storage/' . $this->cover)
            : null;
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? ($this->name_ar ?? $this->name) : $this->name;
    }

    public function getLocalizedBioAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? $this->bio_ar : $this->bio;
    }

    public function getIsLikedByAuthAttribute(): bool
    {
        $userId = auth()->id();
        if (!$userId) return false;
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function getIsFavoritedByAuthAttribute(): bool
    {
        $userId = auth()->id();
        if (!$userId) return false;
        return $this->favorites()->where('user_id', $userId)->exists();
    }

    /** Public URLs of uploaded certification documents (PDF / images). */
    public function getCertificationFilesUrlsAttribute(): array
    {
        return collect($this->certification_files ?? [])
            ->map(fn ($path) => asset('storage/' . $path))
            ->values()
            ->all();
    }
}
