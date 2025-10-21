<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'content',
        'posted_by',
        'is_important',
    ];

    protected $casts = [
        'is_important' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
