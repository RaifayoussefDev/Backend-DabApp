<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'guide_id',
        'image_url',
        'caption',
        'order_position',
    ];

    protected $casts = [
        'order_position' => 'integer',
    ];

    // Relations
    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }
}
