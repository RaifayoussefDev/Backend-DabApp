<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * @OA\Schema(
 *     schema="Country",
 *     type="object",
 *     title="Country",
 *     required={"name", "code"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Morocco"),
 *     @OA\Property(property="code", type="string", example="MA"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Country extends Model
{

    protected $fillable = ['name', 'code'];

    // Relation : un pays a plusieurs villes
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function currencyExchangeRate()
    {
        return $this->hasOne(CurrencyExchangeRate::class, 'country_id');
    }
}
