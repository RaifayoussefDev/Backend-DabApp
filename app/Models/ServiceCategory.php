<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'description',
        'description_ar',
        'icon',
        'color',
        'is_active',
        'order_position'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_position' => 'integer'
    ];

    // Relations
    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function promoCodes()
    {
        return $this->hasMany(ServicePromoCode::class, 'service_category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_position');
    }

    // Accessors
    public function getLocalizedNameAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name;
    }

    public function getLocalizedDescriptionAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description;
    }
}