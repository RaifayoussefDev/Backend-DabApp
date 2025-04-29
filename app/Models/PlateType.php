<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlateType extends Model
{
    protected $fillable = ['country_id', 'name'];

    public function colors(): HasMany
    {
        return $this->hasMany(PlateColor::class, 'type_id');
    }
}
