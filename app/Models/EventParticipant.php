<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EventParticipant",
 *     type="object",
 *     title="EventParticipant",
 *     required={"event_id", "user_id", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="status", type="string", enum={"registered", "confirmed", "attended", "cancelled"}, example="registered"),
 *     @OA\Property(property="registration_date", type="string", format="date-time"),
 *     @OA\Property(property="confirmation_date", type="string", format="date-time"),
 *     @OA\Property(property="payment_status", type="string", enum={"pending", "paid", "refunded"}, example="paid"),
 *     @OA\Property(property="payment_amount", type="number", format="double", example=99.99),
 *     @OA\Property(property="notes", type="string", example="Vegan meal preference"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 */
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

