<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparePartMotorcycle extends Model
{
    protected $fillable = [
        'spare_part_id',
        'brand_id',
        'model_id',
        'year_id',
    ];

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }

    public function brand()
    {
        return $this->belongsTo(MotorcycleBrand::class);
    }

    public function model()
    {
        return $this->belongsTo(MotorcycleModel::class);
    }

    public function year()
    {
        return $this->belongsTo(MotorcycleYear::class);
    }
}
