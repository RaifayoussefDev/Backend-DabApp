<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="ReportType",
 *     type="object",
 *     title="Report Type",
 *     required={"code", "name_en", "name_ar"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="guide"),
 *     @OA\Property(property="name_en", type="string", example="Guide"),
 *     @OA\Property(property="name_ar", type="string", example="دليل"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ReportType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_en',
        'name_ar',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function reasons()
    {
        return $this->hasMany(ReportReason::class);
    }
}
