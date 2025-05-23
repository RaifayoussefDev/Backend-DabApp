<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorcycleYear extends Model
{
    use HasFactory;

    protected $fillable = ['model_id', 'year'];

    public function model()
    {
        return $this->belongsTo(MotorcycleModel::class);
    }
}
