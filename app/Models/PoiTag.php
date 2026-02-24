<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PoiTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the POIs associated with this tag.
     */
    public function pois(): BelongsToMany
    {
        return $this->belongsToMany(PointOfInterest::class, 'poi_tag_relations', 'tag_id', 'poi_id')
            ->withTimestamps();
    }

    /**
     * Set the name and slug attributes.
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = \Illuminate\Support\Str::slug($value);
    }
}
