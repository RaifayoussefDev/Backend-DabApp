<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventSponsorRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'sponsor_id',
        'sponsorship_level',
    ];

    /**
     * Get the event that this sponsor relation belongs to
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Get the sponsor that this relation belongs to
     */
    public function sponsor()
    {
        return $this->belongsTo(EventSponsor::class, 'sponsor_id');
    }

    /**
     * Scope to filter by sponsorship level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('sponsorship_level', $level);
    }

    /**
     * Scope to get platinum sponsors
     */
    public function scopePlatinum($query)
    {
        return $query->where('sponsorship_level', 'platinum');
    }

    /**
     * Scope to get gold sponsors
     */
    public function scopeGold($query)
    {
        return $query->where('sponsorship_level', 'gold');
    }

    /**
     * Scope to get silver sponsors
     */
    public function scopeSilver($query)
    {
        return $query->where('sponsorship_level', 'silver');
    }

    /**
     * Scope to get bronze sponsors
     */
    public function scopeBronze($query)
    {
        return $query->where('sponsorship_level', 'bronze');
    }

    /**
     * Get the display name for the sponsorship level
     */
    public function getLevelNameAttribute()
    {
        $levels = [
            'platinum' => 'Platinum',
            'gold' => 'Gold',
            'silver' => 'Silver',
            'bronze' => 'Bronze',
        ];

        return $levels[$this->sponsorship_level] ?? 'Standard';
    }

    /**
     * Get the color associated with the sponsorship level
     */
    public function getLevelColorAttribute()
    {
        $colors = [
            'platinum' => '#E5E4E2',
            'gold' => '#FFD700',
            'silver' => '#C0C0C0',
            'bronze' => '#CD7F32',
        ];

        return $colors[$this->sponsorship_level] ?? '#6C757D';
    }
}
