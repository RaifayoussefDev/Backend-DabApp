<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="ExpertiseType",
 *     type="object",
 *     title="ExpertiseType",
 *     description="Type of expertise a helper can offer",
 *     @OA\Property(property="id",      type="integer", example=1),
 *     @OA\Property(property="name",    type="string",  example="tire_repair",
 *         description="Unique slug used internally"),
 *     @OA\Property(property="name_en", type="string",  example="Tire Repair",
 *         description="Human-readable English label"),
 *     @OA\Property(property="name_ar", type="string",  example="إصلاح الإطارات",
 *         description="Human-readable Arabic label"),
 *     @OA\Property(property="icon",    type="string",  example="tire_repair"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ExpertiseType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'name_en', 'name_ar', 'icon'];

    // ── Relations ────────────────────────────────────────────────────────────

    public function helperExpertises()
    {
        return $this->hasMany(HelperExpertise::class, 'expertise_type_id');
    }

    public function helpers()
    {
        return $this->belongsToMany(
            HelperProfile::class,
            'helper_expertises',
            'expertise_type_id',
            'helper_profile_id'
        );
    }

    public function assistanceRequests()
    {
        return $this->hasMany(AssistanceRequest::class, 'expertise_type_id');
    }
}
