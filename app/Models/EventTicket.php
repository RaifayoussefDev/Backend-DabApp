<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'ticket_type',
        'price',
        'quantity_available',
        'quantity_sold',
        'is_active',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function purchases()
    {
        return $this->hasMany(EventTicketPurchase::class, 'ticket_id');
    }

    public function isAvailable()
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->quantity_available === null) {
            return true;
        }

        return $this->quantity_sold < $this->quantity_available;
    }

    public function remainingQuantity()
    {
        if ($this->quantity_available === null) {
            return null;
        }

        return max(0, $this->quantity_available - $this->quantity_sold);
    }
}
