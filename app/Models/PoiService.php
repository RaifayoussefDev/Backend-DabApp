<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PoiService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type_id',
    ];

    /**
     * Get the POI type this service belongs to.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PoiType::class, 'type_id');
    }

    /**
     * Get the POIs offering this service.
     */
    public function pointsOfInterest(): BelongsToMany
    {
        return $this->belongsToMany(PointOfInterest::class, 'poi_service_relations', 'service_id', 'poi_id')
            ->withPivot('price');
    }
}
