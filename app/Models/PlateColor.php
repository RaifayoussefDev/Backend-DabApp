<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlateColor extends Model
{

    protected $fillable = ['type_id', 'name'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(PlateType::class, 'type_id');
    }
}
