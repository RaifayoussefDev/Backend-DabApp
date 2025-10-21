<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'contact_type',
        'name',
        'phone',
        'email',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
