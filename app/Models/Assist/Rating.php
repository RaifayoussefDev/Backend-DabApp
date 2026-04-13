<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * @OA\Schema(
 *     schema="AssistRating",
 *     type="object",
 *     title="AssistRating",
 *     description="Rating given after a completed assistance request",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="request_id", type="integer", example=1),
 *     @OA\Property(property="rater_id", type="integer", example=1),
 *     @OA\Property(property="rated_id", type="integer", example=2),
 *     @OA\Property(property="stars", type="integer", minimum=1, maximum=5, example=5),
 *     @OA\Property(property="comment", type="string", nullable=true, example="Very fast and professional!")
 * )
 */
class Rating extends Model
{
    use HasFactory;

    protected $table = 'assist_ratings';

    protected $fillable = ['request_id', 'rater_id', 'rated_id', 'stars', 'comment'];

    protected $casts = [
        'stars' => 'integer',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function request()
    {
        return $this->belongsTo(AssistanceRequest::class, 'request_id');
    }

    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function rated()
    {
        return $this->belongsTo(User::class, 'rated_id');
    }
}
