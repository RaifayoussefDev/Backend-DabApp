<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EventSponsor",
 *     type="object",
 *     title="EventSponsor",
 *     required={"name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Tech Corp"),
 *     @OA\Property(property="logo", type="string", format="url", example="https://example.com/logo.png"),
 *     @OA\Property(property="website", type="string", format="url", example="https://example.com"),
 *     @OA\Property(property="description", type="string", example="Technology provider"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class EventSponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo',
        'website',
        'description',
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_sponsor_relations', 'sponsor_id', 'event_id')
            ->withPivot('sponsorship_level')
            ->withTimestamps();
    }
}
