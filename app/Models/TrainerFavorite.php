<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerFavorite extends Model
{
    use HasFactory;

    protected $fillable = ['trainer_id', 'user_id'];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
