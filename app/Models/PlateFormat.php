<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlateFormat extends Model
{
    protected $fillable = [
        'name', 'country_id', 'city_id', 'is_active', 'background_color', 'text_color',
        'width_mm', 'height_mm', 'description'
    ];


    public function fields()
    {
        return $this->hasMany(PlateFormatField::class, 'plate_format_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }


}

