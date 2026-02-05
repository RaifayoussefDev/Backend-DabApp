<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="EventReview",
 *     type="object",
 *     title="EventReview",
 *     required={"event_id", "user_id", "rating", "comment"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
 *     @OA\Property(property="comment", type="string", example="Great event!"),
 *     @OA\Property(property="is_approved", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 */
class EventReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'user_id',
        'rating',
        'comment',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'rating' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', 1);
    }
}
