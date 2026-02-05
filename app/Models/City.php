<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *     schema="City",
 *     type="object",
 *     title="City",
 *     required={"name", "country_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Casablanca"),
 *     @OA\Property(property="name_ar", type="string", example="الدار البيضاء"),
 *     @OA\Property(property="country_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="country", ref="#/components/schemas/Country")
 * )
 */
class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'country_id',
    ];

    // Define the relationship with Country
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    // Optional: If listings reference the city
    public function listings()
    {
        return $this->hasMany(Listing::class);
    }

    public function plateFormats()
    {
        return $this->hasMany(PlateFormat::class);
    }

    /**
     * Relation avec les formats de plaques actifs uniquement
     */
    public function activePlateFormats()
    {
        return $this->hasMany(PlateFormat::class)->where('is_active', true);
    }
}
