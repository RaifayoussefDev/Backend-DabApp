<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceProviderImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'image_url',
        'caption',
        'caption_ar',
        'order_position',
        'is_featured'
    ];

    protected $casts = [
        'order_position' => 'integer',
        'is_featured' => 'boolean'
    ];

    // Relations
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    // Scopes
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_position');
    }

    // Accessors
    public function getLocalizedCaptionAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->caption_ar : $this->caption;
    }

    public function getFullImageUrlAttribute()
    {
        return $this->image_url ? asset('storage/' . $this->image_url) : null;
    }
}