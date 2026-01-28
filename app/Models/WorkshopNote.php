<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'notes',
        'notes_ar'
    ];

    // Relations
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    // Accessors
    public function getLocalizedNotesAttribute()
    {
        return app()->getLocale() === 'ar' ? $this->notes_ar : $this->notes;
    }

    public function getFormattedNotesAttribute()
    {
        return nl2br(e($this->localized_notes));
    }

    public function getNotesPreviewAttribute()
    {
        $notes = $this->localized_notes;
        return strlen($notes) > 200 ? substr($notes, 0, 200) . '...' : $notes;
    }
}