<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BikePartBrand extends Model
{
    use HasFactory;

    protected $table = 'bike_part_brands';

    protected $fillable = ['name'];

    // If you want to disable timestamps
    // public $timestamps = false;
}