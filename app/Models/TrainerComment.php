<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'user_id',
        'parent_id',
        'content',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(TrainerComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(TrainerComment::class, 'parent_id')->where('is_approved', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeRootOnly($query)
    {
        return $query->whereNull('parent_id');
    }
}
