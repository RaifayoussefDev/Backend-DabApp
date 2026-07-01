<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainerEquipment extends Model
{
    protected $table = 'trainer_equipment';

    protected $fillable = [
        'trainer_id',
        'equipment_type_id',
        'name',
        'name_ar',
        'icon',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }
}
