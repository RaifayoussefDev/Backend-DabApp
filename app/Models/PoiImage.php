<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoiImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'poi_id',
        'image_url',
        'is_main',
        'order_position',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    /**
     * Get the POI that owns this image.
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }
}
