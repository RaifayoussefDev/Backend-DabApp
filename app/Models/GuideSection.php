<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'guide_id',
        'type',
        'title',
        'title_ar',
        'description',
        'description_ar',
        'image_url',
        'image_position',
        'media',
        'order_position',
    ];

    protected $casts = [
        'media' => 'array',
        'order_position' => 'integer',
    ];

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_position');
    }
}
