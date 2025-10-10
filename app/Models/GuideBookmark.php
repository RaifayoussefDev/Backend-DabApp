<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideBookmark extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'guide_id',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relations
    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
