<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EventTicket",
 *     type="object",
 *     title="EventTicket",
 *     required={"event_id", "ticket_type", "price"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="ticket_type", type="string", example="General Admission"),
 *     @OA\Property(property="price", type="number", format="double", example=50.00),
 *     @OA\Property(property="quantity_available", type="integer", example=100),
 *     @OA\Property(property="quantity_sold", type="integer", example=10),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="description", type="string", example="Standard entry ticket"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
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
