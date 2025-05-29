<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoomNegotiation extends Model
{
    public $timestamps = false; // since we only use created_at

    protected $fillable = [
        'submission_id', 'sender_id', 'receiver_id', 'offer_amount', 'response', 'created_at'
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}

