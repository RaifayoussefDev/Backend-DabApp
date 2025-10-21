<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'registration_date',
        'confirmation_date',
        'payment_status',
        'payment_amount',
        'notes',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
        'confirmation_date' => 'datetime',
        'payment_amount' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticketPurchases()
    {
        return $this->hasMany(EventTicketPurchase::class, 'participant_id');
    }
}

