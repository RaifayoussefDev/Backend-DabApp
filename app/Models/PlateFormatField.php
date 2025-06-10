<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlateFormatField extends Model
{
    protected $fillable = [
        'plate_format_id', 'field_name', 'position', 'character_type',
        'writing_system', 'min_length', 'max_length', 'is_required',
        'validation_pattern', 'font_size', 'is_bold', 'display_order'
    ];

    public function format()
    {
        return $this->belongsTo(PlateFormat::class);
    }
}

