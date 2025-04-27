<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorcycleModel extends Model
{
    use HasFactory;

    protected $fillable = ['brand_id', 'name', 'type_id'];

    public function brand()
    {
        return $this->belongsTo(MotorcycleBrand::class);
    }

    public function type()
    {
        return $this->belongsTo(MotorcycleType::class);
    }
}
