<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventFaq extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'question',
        'question_ar',
        'answer',
        'answer_ar',
        'order_position',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
