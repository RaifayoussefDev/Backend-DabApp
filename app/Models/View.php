<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class View extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'viewable_id',
        'viewable_type',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the parent viewable model (Guide, PointOfInterest, Event, etc.).
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who viewed the content.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
