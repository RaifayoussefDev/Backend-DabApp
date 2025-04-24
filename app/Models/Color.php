<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $fillable = ['name'];

    // You can add relationships here if needed, for example:
    // public function products()
    // {
    //     return $this->hasMany(Product::class);
    // }
}
