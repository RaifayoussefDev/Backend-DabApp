<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionResponse extends Model
{
    protected $fillable = ['submission_id', 'buyer_id', 'response', 'response_date'];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}

