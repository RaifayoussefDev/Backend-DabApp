<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'listing_id', 'user_id', 'amount',
        'submission_date', 'status', 'min_soom'
    ];



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
}

