<?php

namespace App\Models\Assist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['request_id', 'path'];

    // ── Relations ────────────────────────────────────────────────────────────

    public function request()
    {
        return $this->belongsTo(AssistanceRequest::class, 'request_id');
    }
}
