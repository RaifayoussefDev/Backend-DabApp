<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoiType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'color',
    ];

    /**
     * Get the points of interest for this type.
     */
    public function pointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class, 'type_id');
    }

    /**
     * Get the services for this type.
     */
    public function services(): HasMany
    {
        return $this->hasMany(PoiService::class, 'type_id');
    }
}
