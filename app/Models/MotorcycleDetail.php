<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorcycleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_id',
        'displacement',
        'engine_type',
        'engine_details',
        'power',
        'torque',
        'top_speed',
        'quarter_mile',
        'acceleration_0_100',
        'max_rpm',
        'compression',
        'bore_stroke',
        'valves_per_cylinder',
        'fuel_system',
        'gearbox',
        'transmission_type',
        'front_suspension',
        'rear_suspension',
        'front_tire',
        'rear_tire',
        'front_brakes',
        'rear_brakes',
        'dry_weight',
        'wet_weight',
        'seat_height',
        'overall_length',
        'overall_width',
        'overall_height',
        'ground_clearance',
        'wheelbase',
        'fuel_capacity',
        'rating',
        'price',
        // New fields
        'fuel_control',
        'ignition',
        'lubrication_system',
        'cooling_system',
        'clutch',
        'driveline',
        'fuel_consumption',
        'greenhouse_gases',
        'emission_details',
        'exhaust_system',
        'frame_type',
        'rake',
        'trail',
        'front_wheel_travel',
        'rear_wheel_travel',
        'front_brakes_diameter',
        'rear_brakes_diameter',
        'wheels',
        'seat',
        'power_weight_ratio',
        'front_weight_percentage',
        'rear_weight_percentage',
        'alternate_seat_height',
        'carrying_capacity',
        'color_options',
        'starter',
        'instruments',
        'electrical',
        'light',
        'factory_warranty',
        'comments',
        'acceleration_60_140'
    ];

    public function year()
    {
        return $this->belongsTo(MotorcycleYear::class, 'year_id');
    }
}