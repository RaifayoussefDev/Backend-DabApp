<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RouteTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the routes that have this tag.
     */
    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_tag_relations', 'tag_id', 'route_id');
    }
}
