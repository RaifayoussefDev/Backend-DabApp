<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'user_id',
        'completed_at',
        'actual_duration',
        'notes',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * Get the route that was completed.
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    /**
     * Get the user who completed the route.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
