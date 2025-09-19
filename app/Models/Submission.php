<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'sale_validation_date'      // New: When sale was validated
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
