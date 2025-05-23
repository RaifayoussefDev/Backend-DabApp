<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRulesLicencePlate extends Model
{
    use HasFactory;

    protected $table = 'pricing_rules_licence_plate';

    protected $fillable = [
        'price',
        // 'plate_type_id', // Uncomment if you still use it
    ];
}
