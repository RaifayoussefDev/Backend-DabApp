<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_en',
        'name_ar',
        'slug',
        'description',
        'required_certifications',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'required_certifications' => 'array',
        'sort_order'              => 'integer',
        'is_active'               => 'boolean',
    ];

    public function approvals()
    {
        return $this->hasMany(TrainerLevelApproval::class, 'level_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function courses()
    {
        return $this->hasMany(TrainerCourse::class, 'level_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? ($this->name_ar ?? $this->name_en) : $this->name_en;
    }
}
