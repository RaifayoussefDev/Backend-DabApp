<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     title="ServiceCategory",
 *     description="Service Category model",
 *     @OA\Xml(name="ServiceCategory")
 * )
 */
class ServiceCategory extends Model
{
    use HasFactory;

    /**
     * @OA\Property(property="id", type="integer", example=1)
     * @OA\Property(property="name", type="string", example="Bike Transport")
     * @OA\Property(property="name_ar", type="string", example="نقل الدراجات")
     * @OA\Property(property="slug", type="string", example="bike-transport")
     * @OA\Property(property="description", type="string", nullable=true)
     * @OA\Property(property="description_ar", type="string", nullable=true)
     * @OA\Property(property="icon", type="string", nullable=true)
     * @OA\Property(property="color", type="string", nullable=true)
     * @OA\Property(property="is_active", type="boolean", example=true)
     * @OA\Property(property="order_position", type="integer", example=1)
     * @OA\Property(property="created_at", type="string", format="date-time")
     * @OA\Property(property="updated_at", type="string", format="date-time")
     */
    protected $fillable = [
        'name',
        'name_ar',
        'slug',
        'description',
        'description_ar',
        'icon',
        'color',
        'is_active',
        'order_position'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_position' => 'integer'
    ];

    // Relations
    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function promoCodes()
    {
        return $this->hasMany(ServicePromoCode::class, 'service_category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_position');
    }

    // Accessors
    public function getLocalizedNameAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name;
    }

    public function getLocalizedDescriptionAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description;
    }
}