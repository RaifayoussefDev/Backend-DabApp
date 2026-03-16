<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorcycleBrand extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_displayed'];

    public function models()
    {
        return $this->hasMany(MotorcycleModel::class, 'brand_id');
    }

    public function motorcycles()
    {
        return $this->hasMany(Motorcycle::class);
    }
}
