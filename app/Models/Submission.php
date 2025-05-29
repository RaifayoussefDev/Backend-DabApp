<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'listing_id', 'user_id', 'option_id', 'amount',
        'submission_date', 'status', 'min_soom'
    ];

    public function option()
    {
        return $this->belongsTo(SubmissionOption::class, 'option_id');
    }

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
}

