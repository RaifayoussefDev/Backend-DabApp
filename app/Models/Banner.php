<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image',
        'link',
        'ad_id',
        'order',
        'is_active',
        'start_date',
        'end_date',
        // Ad fields
        'type',
        'media_url',
        'button_text',
        'button_ar',
        'title_ar',
        'description_ar',
        'has_form',
        'google_sheet_id',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'has_form'   => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function ad()
    {
        return $this->belongsTo(Banner::class, 'ad_id');
    }

    public function adSubmissions()
    {
        return $this->hasMany(AdSubmission::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->orderBy('order');
    }
}
