<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorcycleType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'name_ar', 'description', 'icon'];

    public function models()
    {
        return $this->hasMany(MotorcycleModel::class, 'type_id');
    }
}
