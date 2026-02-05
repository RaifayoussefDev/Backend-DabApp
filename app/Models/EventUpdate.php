<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EventUpdate",
 *     type="object",
 *     title="EventUpdate",
 *     required={"event_id", "title", "content"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Update Title"),
 *     @OA\Property(property="content", type="string", example="Update content details"),
 *     @OA\Property(property="posted_by", type="integer", example=1),
 *     @OA\Property(property="is_important", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="posted_by_user", ref="#/components/schemas/User")
 * )
 */
class EventUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'content',
        'posted_by',
        'is_important',
    ];

    protected $casts = [
        'is_important' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
