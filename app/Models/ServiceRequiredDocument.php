<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequiredDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'document_name',
        'document_name_ar',
        'description',
        'description_ar',
        'is_required',
        'order_position'
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'order_position' => 'integer'
    ];

    // Relations
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // Scopes
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_position');
    }

    // Accessors
    public function getLocalizedDocumentNameAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->document_name_ar : $this->document_name;
    }

    public function getLocalizedDescriptionAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description;
    }

    public function getRequiredLabelAttribute()
    {
        if ($this->is_required) {
            return app()->getLocale() === 'ar' ? 'مطلوب' : 'Required';
        }
        return app()->getLocale() === 'ar' ? 'اختياري' : 'Optional';
    }
}