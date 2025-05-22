<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyExchangeRate extends Model
{
    use HasFactory;

    protected $table = 'currency_exchange_rates';

    protected $fillable = [
        'country_id',
        'currency_code',
        'currency_symbol',
        'exchange_rate',
    ];

    /**
     * Relation avec le pays
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
