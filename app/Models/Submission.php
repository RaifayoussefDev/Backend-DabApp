<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * @OA\Schema(
 *     schema="Submission",
 *     type="object",
 *     title="Submission",
 *     description="Submission (SOOM) model representing a bid on a listing",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="listing_id", type="integer", example=10),
 *     @OA\Property(property="user_id", type="integer", example=2),
 *     @OA\Property(property="amount", type="number", format="float", example=1500.50),
 *     @OA\Property(property="status", type="string", enum={"pending", "accepted", "rejected"}),
 *     @OA\Property(property="submission_date", type="string", format="date-time"),
 *     @OA\Property(property="acceptance_date", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="sale_validated", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Submission extends Model
{
    protected $fillable = [
        'listing_id',
        'user_id',
        'amount',
        'submission_date',
        'status',
        'min_soom',
        'acceptance_date',           // New: When SOOM was accepted
        'rejection_reason',          // New: Reason for rejection (optional)
        'sale_validated',           // New: Boolean for sale validation
        'sale_validation_date',      // New: When sale was validated
        'isOverbidding'
    ];

    protected $casts = [
        'submission_date' => 'datetime',
        'acceptance_date' => 'datetime',
        'sale_validation_date' => 'datetime',
        'sale_validated' => 'boolean',
        'amount' => 'decimal:2'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function responses()
    {
        return $this->hasMany(SubmissionResponse::class);
    }

    public function negotiations()
    {
        return $this->hasMany(SoomNegotiation::class);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeValidated($query)
    {
        return $query->where('sale_validated', true);
    }

    public function scopePendingValidation($query)
    {
        return $query->where('status', 'accepted')
                    ->where('sale_validated', false);
    }

    // Helper methods
    public function isValidationExpired()
    {
        if (!$this->acceptance_date) {
            return false;
        }

        $deadline = Carbon::parse($this->acceptance_date)->addDays(5);
        return now()->gt($deadline);
    }

    public function getValidationDeadline()
    {
        if (!$this->acceptance_date) {
            return null;
        }

        return Carbon::parse($this->acceptance_date)->addDays(5);
    }

    public function canBeValidated()
    {
        return $this->status === 'accepted'
            && !$this->sale_validated
            && !$this->isValidationExpired();
    }
}
