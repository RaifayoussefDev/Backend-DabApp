<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerTrainingBike extends Model
{
    use HasFactory;

    protected $table = 'trainer_training_bikes';

    protected $fillable = [
        'trainer_id',
        'garage_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function garage()
    {
        return $this->belongsTo(MyGarage::class, 'garage_id');
    }
}
