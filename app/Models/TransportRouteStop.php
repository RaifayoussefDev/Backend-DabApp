<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportRouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'stop_name',
        'stop_name_ar',
        'stop_order',
        'arrival_time',
        'departure_time',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'stop_order' => 'integer',
    ];

    // Relations
    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }
}
