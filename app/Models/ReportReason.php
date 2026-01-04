<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="ReportReason",
 *     type="object",
 *     title="Report Reason",
 *     required={"report_type_id", "label_en", "label_ar"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="report_type_id", type="integer", example=1),
 *     @OA\Property(property="label_en", type="string", example="Spam"),
 *     @OA\Property(property="label_ar", type="string", example="محتوى غير مرغوب فيه"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="type", ref="#/components/schemas/ReportType")
 * )
 */
class ReportReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type_id',
        'label_en',
        'label_ar',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function type()
    {
        return $this->belongsTo(ReportType::class, 'report_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
