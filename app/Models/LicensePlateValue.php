<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicensePlateValue extends Model
{
    protected $fillable = [
        'license_plate_id',
        'plate_format_field_id',
        'field_value',
    ];

    public function licensePlate(): BelongsTo
    {
        return $this->belongsTo(LicensePlate::class);
    }

    public function formatField(): BelongsTo
    {
        return $this->belongsTo(PlateFormatField::class, 'plate_format_field_id');
    }
}
