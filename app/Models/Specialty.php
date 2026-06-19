<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Specialty extends Model
{
    use HasFactory;

    protected $fillable = [
        'libelle_en',
        'libelle_ar',
        'icon',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['icon_url', 'localized_label'];

    // ---------------------------------------------------------------
    // Boot
    // ---------------------------------------------------------------

    protected static function booted(): void
    {
        static::creating(function (Specialty $specialty) {
            if (empty($specialty->slug)) {
                $specialty->slug = Str::slug($specialty->libelle_en);
            }
        });

        static::updating(function (Specialty $specialty) {
            if ($specialty->isDirty('libelle_en') && empty($specialty->slug)) {
                $specialty->slug = Str::slug($specialty->libelle_en);
            }
        });
    }

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function trainers()
    {
        return $this->belongsToMany(Trainer::class, 'trainer_specialty');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon ? asset('storage/' . $this->icon) : null;
    }

    public function getLocalizedLabelAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->libelle_ar : $this->libelle_en;
    }
}
