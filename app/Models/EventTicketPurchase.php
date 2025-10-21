<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTicketPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'participant_id',
        'quantity',
        'total_price',
        'purchase_date',
        'qr_code',
        'checked_in_at',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'purchase_date' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(EventTicket::class, 'ticket_id');
    }

    public function participant()
    {
        return $this->belongsTo(EventParticipant::class, 'participant_id');
    }
}
