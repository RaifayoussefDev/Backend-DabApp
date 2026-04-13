<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * @OA\Schema(
 *     schema="AssistNotification",
 *     type="object",
 *     title="AssistNotification",
 *     description="In-app notification for the Velocity Assist module",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="request_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="type", type="string", enum={"new_request","accepted","en_route","arrived","completed","rated"}, example="new_request"),
 *     @OA\Property(property="title", type="string", example="New assistance request nearby"),
 *     @OA\Property(property="body", type="string", example="Someone needs tire repair 2.3 km away"),
 *     @OA\Property(property="is_read", type="boolean", example=false)
 * )
 */
class AssistNotification extends Model
{
    use HasFactory;

    protected $table = 'assist_notifications';

    protected $fillable = [
        'user_id',
        'request_id',
        'type',
        'title',
        'body',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function request()
    {
        return $this->belongsTo(AssistanceRequest::class, 'request_id');
    }
}
