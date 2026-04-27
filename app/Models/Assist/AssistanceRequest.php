<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\MyGarage;

/**
 * @OA\Schema(
 *     schema="AssistanceRequest",
 *     type="object",
 *     title="AssistanceRequest",
 *     description="A roadside assistance request",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="seeker_id", type="integer", example=1),
 *     @OA\Property(property="helper_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="motorcycle_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="expertise_type_id", type="integer", example=1),
 *     @OA\Property(property="status", type="string", enum={"pending","accepted","en_route","arrived","completed","cancelled"}, example="pending"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="latitude", type="number", format="float", example=24.7136),
 *     @OA\Property(property="longitude", type="number", format="float", example=46.6753),
 *     @OA\Property(property="location_label", type="string", example="King Fahd Road, Riyadh"),
 *     @OA\Property(property="status_label", type="object",
 *         @OA\Property(property="en", type="string", example="Pending"),
 *         @OA\Property(property="ar", type="string", example="قيد الانتظار")
 *     ),
 *     @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="arrived_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @property int                         $id
 * @property int                         $seeker_id
 * @property int|null                    $helper_id
 * @property int|null                    $motorcycle_id
 * @property int                         $expertise_type_id
 * @property string                      $status
 * @property string|null                 $description
 * @property string                      $latitude
 * @property string                      $longitude
 * @property string                      $location_label
 * @property \Carbon\Carbon|null         $accepted_at
 * @property \Carbon\Carbon|null         $arrived_at
 * @property \Carbon\Carbon|null         $completed_at
 * @property \Carbon\Carbon|null         $cancelled_at
 * @property string|null                 $cancel_reason
 * @property array                       $status_label
 */
class AssistanceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['status_label'];

    protected $fillable = [
        'seeker_id',
        'helper_id',
        'motorcycle_id',
        'status',
        'description',
        'latitude',
        'longitude',
        'location_label',
        'accepted_at',
        'arrived_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
        'accepted_at'  => 'datetime',
        'arrived_at'   => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ── Accessors ────────────────────────────────────────────────────────────

    private const STATUS_LABELS = [
        'pending'   => ['en' => 'Pending',    'ar' => 'قيد الانتظار'],
        'accepted'  => ['en' => 'Accepted',   'ar' => 'مقبول'],
        'en_route'  => ['en' => 'On the Way', 'ar' => 'في الطريق'],
        'arrived'   => ['en' => 'Arrived',    'ar' => 'وصل'],
        'completed' => ['en' => 'Completed',  'ar' => 'مكتمل'],
        'cancelled' => ['en' => 'Cancelled',  'ar' => 'ملغى'],
    ];

    public function getStatusLabelAttribute(): array
    {
        return self::STATUS_LABELS[$this->status] ?? ['en' => $this->status, 'ar' => $this->status];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function seeker()
    {
        return $this->belongsTo(User::class, 'seeker_id');
    }

    public function helper()
    {
        return $this->belongsTo(User::class, 'helper_id');
    }

    public function motorcycle()
    {
        return $this->belongsTo(MyGarage::class, 'motorcycle_id');
    }

    public function expertiseTypes()
    {
        return $this->belongsToMany(ExpertiseType::class, 'assistance_request_expertise');
    }

    public function photos()
    {
        return $this->hasMany(RequestPhoto::class, 'request_id');
    }

    public function rating()
    {
        return $this->hasOne(Rating::class, 'request_id');
    }

    public function notifications()
    {
        return $this->hasMany(AssistNotification::class, 'request_id');
    }
}
