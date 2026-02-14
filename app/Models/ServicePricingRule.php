<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Service;
use App\Models\City;

class ServicePricingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'type',
        'price',
        'origin_city_id',
        'destination_city_id',
        'is_active',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function originCity()
    {
        return $this->belongsTo(City::class, 'origin_city_id');
    }

    public function destinationCity()
    {
        return $this->belongsTo(City::class, 'destination_city_id');
    }
}
