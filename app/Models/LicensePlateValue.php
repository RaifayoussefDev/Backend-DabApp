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

    /**
     * ✅ CORRECTION : Spécifier les clés explicitement
     * 
     * Foreign key : license_plate_id (dans cette table)
     * Owner key : id (dans la table license_plates)
     */
    public function licensePlate(): BelongsTo
    {
        return $this->belongsTo(
            LicensePlate::class,
            'license_plate_id',  // ← FK dans license_plate_values
            'id'                 // ← PK dans license_plates
        );
    }

    /**
     * Relation avec PlateFormatField
     */
    public function formatField(): BelongsTo
    {
        return $this->belongsTo(
            PlateFormatField::class,
            'plate_format_field_id',  // ← FK dans license_plate_values
            'id'                      // ← PK dans plate_format_fields
        );
    }

    /**
     * Alias pour la relation formatField
     */
    public function field(): BelongsTo
    {
        return $this->formatField();
    }

    /**
     * Alias pour la relation formatField
     */
    public function plateFormatField(): BelongsTo
    {
        return $this->formatField();
    }
}