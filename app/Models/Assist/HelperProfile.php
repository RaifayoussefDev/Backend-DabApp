<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Country;
use App\Models\City;

/**
 * @OA\Schema(
 *     schema="HelperProfile",
 *     type="object",
 *     title="HelperProfile",
 *     description="Helper profile for Velocity Assist",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="is_available", type="boolean", example=true),
 *     @OA\Property(property="is_verified", type="boolean", example=false),
 *     @OA\Property(property="rating", type="number", format="float", example=4.75),
 *     @OA\Property(property="total_assists", type="integer", example=12),
 *     @OA\Property(property="service_radius_km", type="integer", example=15),
 *     @OA\Property(property="level", type="string", enum={"standard","elite","vanguard"}, example="standard"),
 *     @OA\Property(property="latitude", type="number", format="float", example=24.7136),
 *     @OA\Property(property="longitude", type="number", format="float", example=46.6753),
 *     @OA\Property(property="country_id",        type="integer", nullable=true, example=1),
 *     @OA\Property(property="city_id",           type="integer", nullable=true, example=3),
 *     @OA\Property(property="terms_accepted_at", type="string",  format="date-time", nullable=true,
 *         description="Timestamp when the helper accepted the terms and conditions. Null if not yet accepted."),
 *     @OA\Property(property="notify_push",       type="boolean", example=true,
 *         description="Receive push notifications for new requests"),
 *     @OA\Property(property="notify_whatsapp",   type="boolean", example=false,
 *         description="Receive WhatsApp notifications for new requests"),
 *     @OA\Property(property="notify_email",      type="boolean", example=false,
 *         description="Receive email notifications for new requests"),
 *     @OA\Property(property="instagram_url",     type="string",  nullable=true, example="https://instagram.com/ahmed_helper"),
 *     @OA\Property(property="facebook_url",      type="string",  nullable=true, example="https://facebook.com/ahmed.helper"),
 *     @OA\Property(property="linkedin_url",      type="string",  nullable=true, example="https://linkedin.com/in/ahmed-helper"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class HelperProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'is_available',
        'is_verified',
        'rating',
        'total_assists',
        'service_radius_km',
        'level',
        'latitude',
        'longitude',
        'country_id',
        'city_id',
        'terms_accepted_at',
        'notify_push',
        'notify_whatsapp',
        'notify_email',
        'instagram_url',
        'facebook_url',
        'linkedin_url',
    ];

    protected $casts = [
        'is_available'      => 'boolean',
        'is_verified'       => 'boolean',
        'notify_push'       => 'boolean',
        'notify_whatsapp'   => 'boolean',
        'notify_email'      => 'boolean',
        'rating'            => 'decimal:2',
        'latitude'          => 'decimal:7',
        'longitude'         => 'decimal:7',
        'total_assists'     => 'integer',
        'service_radius_km' => 'integer',
        'terms_accepted_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function expertises()
    {
        return $this->hasMany(HelperExpertise::class, 'helper_profile_id');
    }

    public function expertiseTypes()
    {
        return $this->belongsToMany(
            ExpertiseType::class,
            'helper_expertises',
            'helper_profile_id',
            'expertise_type_id'
        );
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->where('is_verified', true);
    }

    /**
     * Filter helpers within $radius km from ($lat, $lng) using Haversine.
     * Uses parameterized bindings to prevent SQL injection.
     */
    public function scopeNearTo($query, float $lat, float $lng, int $radius = 15)
    {
        $haversine = self::haversineExpression();

        return $query
            ->selectRaw("*, ({$haversine}) AS distance_km", [$lat, $lng, $lat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->having('distance_km', '<=', $radius)
            ->orderBy('distance_km');
    }

    /**
     * Returns the parameterized Haversine SQL expression.
     * Bindings order: lat, lat, lng, lat  (4 positional ? placeholders).
     */
    public static function haversineExpression(): string
    {
        return "6371 * acos(
            cos(radians(?))
            * cos(radians(latitude))
            * cos(radians(longitude) - radians(?))
            + sin(radians(?)) * sin(radians(latitude))
        )";
    }
}
