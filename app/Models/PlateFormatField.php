<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlateFormatField extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate_format_id',
        'field_name',
        'field_name_ar',
        'variable_name',
        'position',
        'character_type',
        'writing_system',
        'min_length',
        'max_length',
        'is_required',
        'validation_pattern',
        'font_size',
        'is_bold',
        'display_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_bold' => 'boolean',
    ];

    /**
     * Relation avec le format de plaque
     */
    public function plateFormat()
    {
        return $this->belongsTo(PlateFormat::class);
    }

    /**
     * Si vous avez une table Position séparée, décommentez cette relation
     * Sinon, position est juste un champ string
     */
    /*
    public function position()
    {
        return $this->belongsTo(Position::class);
    }
    */
}
