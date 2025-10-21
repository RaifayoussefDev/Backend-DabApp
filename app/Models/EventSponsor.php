<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventSponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo',
        'website',
        'description',
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_sponsor_relations', 'sponsor_id', 'event_id')
            ->withPivot('sponsorship_level')
            ->withTimestamps();
    }
}
