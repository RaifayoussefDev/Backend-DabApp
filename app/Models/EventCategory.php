<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EventCategory",
 *     type="object",
 *     title="EventCategory",
 *     required={"name", "slug"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Motocross"),
 *     @OA\Property(property="name_ar", type="string", example="موتوكروس"),
 *     @OA\Property(property="slug", type="string", example="motocross"),
 *     @OA\Property(property="description", type="string", example="Off-road motorcycle racing events"),
 *     @OA\Property(property="icon", type="string", example="fa-motorcycle"),
 *     @OA\Property(property="color", type="string", example="#FF4500"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class EventCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'description',
        'description_ar',
        'icon',
        'color',
    ];

    public function events()
    {
        return $this->hasMany(Event::class, 'category_id');
    }
}
