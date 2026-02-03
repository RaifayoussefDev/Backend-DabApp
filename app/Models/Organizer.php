<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="Organizer",
 *     title="Organizer",
 *     description="Organizer model",
 *     required={"name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Super Event Organizers LLC"),
 *     @OA\Property(property="description", type="string", example="A leading event organization company."),
 *     @OA\Property(property="logo", type="string", format="url", description="URL to the logo image", example="http://example.com/storage/organizers/logo.png"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 */
class Organizer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'logo',
    ];

    public function events()
    {
        return $this->hasMany(Event::class, 'organizer_profile_id');
    }
}
