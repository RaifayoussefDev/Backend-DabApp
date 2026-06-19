<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerGallery extends Model
{
    use HasFactory;

    protected $table = 'trainer_gallery';

    protected $fillable = [
        'trainer_id',
        'path',
        'caption',
        'caption_ar',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        // Thumbnail stored alongside original with _thumb suffix
        $info = pathinfo($this->path);
        $thumb = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
        return asset('storage/' . $thumb);
    }

    public function getLocalizedCaptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' ? ($this->caption_ar ?? $this->caption) : $this->caption;
    }
}
