<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleYear;

/**
 * @OA\Schema(
 *     schema="AssistMotorcycle",
 *     type="object",
 *     title="AssistMotorcycle",
 *     description="User motorcycle registered for assistance requests",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="brand_id", type="integer", example=3),
 *     @OA\Property(property="model_id", type="integer", example=7),
 *     @OA\Property(property="year_id", type="integer", example=12),
 *     @OA\Property(property="color", type="string", example="Red"),
 *     @OA\Property(property="plate_number", type="string", example="ABC 1234"),
 *     @OA\Property(property="plate_country", type="string", enum={"SA","AE","KW","BH","QA","OM"}, example="SA")
 * )
 */
class Motorcycle extends Model
{
    use HasFactory;

    protected $table = 'assist_motorcycles';

    protected $fillable = [
        'user_id',
        'brand_id',
        'model_id',
        'year_id',
        'color',
        'plate_number',
        'plate_country',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function brand()
    {
        return $this->belongsTo(MotorcycleBrand::class, 'brand_id');
    }

    public function model()
    {
        return $this->belongsTo(MotorcycleModel::class, 'model_id');
    }

    public function year()
    {
        return $this->belongsTo(MotorcycleYear::class, 'year_id');
    }

    public function assistanceRequests()
    {
        return $this->hasMany(AssistanceRequest::class, 'motorcycle_id');
    }
}
