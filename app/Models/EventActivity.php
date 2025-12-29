<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'title_ar',
        'description',
        'description_ar',
        'start_time',
        'end_time',
        'location',
        'day_in_event',
        'order_position',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
