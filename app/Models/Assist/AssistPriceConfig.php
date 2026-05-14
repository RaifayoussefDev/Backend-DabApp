<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Model;

class AssistPriceConfig extends Model
{
    protected $table = 'assist_price_configs';

    protected $fillable = ['price_min', 'price_max', 'price_step'];

    protected $casts = [
        'price_min'  => 'integer',
        'price_max'  => 'integer',
        'price_step' => 'integer',
    ];

    /** Returns the single config row, creating it with defaults if missing. */
    public static function current(): self
    {
        return self::firstOrCreate([], [
            'price_min'  => 0,
            'price_max'  => 150,
            'price_step' => 50,
        ]);
    }

    /** Returns all valid price options: 0, 50, 100, 150 … up to price_max. */
    public function validPrices(): array
    {
        $prices = [];
        for ($p = $this->price_min; $p <= $this->price_max; $p += $this->price_step) {
            $prices[] = $p;
        }
        return $prices;
    }

    public function isValidPrice(int $price): bool
    {
        return $price >= $this->price_min
            && $price <= $this->price_max
            && ($this->price_step === 0 || $price % $this->price_step === 0);
    }
}
