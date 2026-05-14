<?php

namespace App\Models\Assist;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AssistProposal extends Model
{
    protected $table = 'assist_proposals';

    protected $fillable = [
        'request_id',
        'helper_id',
        'proposed_price',
        'status',
        'rejection_type',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'proposed_price' => 'integer',
        'accepted_at'    => 'datetime',
        'rejected_at'    => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function request()
    {
        return $this->belongsTo(AssistanceRequest::class, 'request_id');
    }

    public function helper()
    {
        return $this->belongsTo(User::class, 'helper_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
