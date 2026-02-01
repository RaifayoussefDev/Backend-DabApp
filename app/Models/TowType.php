<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     title="TowType",
 *     description="Tow Service Type model",
 *     @OA\Xml(name="TowType")
 * )
 */
class TowType extends Model
{
    use HasFactory;

    /**
     * @OA\Property(property="id", type="integer", example=1)
     * @OA\Property(property="name", type="string", example="Flatbed Towing")
     * @OA\Property(property="base_price", type="number", format="float", example=100.00)
     * @OA\Property(property="price_per_km", type="number", format="float", example=5.00)
     * @OA\Property(property="is_active", type="boolean", example=true)
     */
    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'icon',
        'image',
        'base_price',
        'price_per_km',
        'is_active',
        'order_position'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'price_per_km' => 'decimal:2',
        'is_active' => 'boolean',
        'order_position' => 'integer'
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_position');
    }

    // Helper Methods
    public function calculatePrice($distanceKm)
    {
        $basePrice = $this->base_price ?? 0;
        $pricePerKm = $this->price_per_km ?? 0;

        return $basePrice + ($distanceKm * $pricePerKm);
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

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : asset('images/default-tow.png');
    }

    public function getPricingLabelAttribute()
    {
        $base = number_format($this->base_price, 2) . ' SAR';
        $perKm = number_format($this->price_per_km, 2) . ' SAR/km';

        return $base . ' + ' . $perKm;
    }
}