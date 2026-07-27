<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Append-only audit trail for authentication events (register, login, logout,
 * OTP delivery via WhatsApp/email) — distinct from Laravel's text log files,
 * this is queryable/listable from the admin panel.
 */
class AuthLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'success',
        'method',
        'identifier',
        'message',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Convenience recorder — pulls IP/user-agent off the current request so
     * call sites don't each have to thread that through.
     */
    public static function record(
        string $event,
        bool $success,
        ?int $userId = null,
        ?string $method = null,
        ?string $identifier = null,
        ?string $message = null,
        ?Request $request = null
    ): self {
        $request = $request ?? request();

        return static::create([
            'user_id' => $userId,
            'event' => $event,
            'success' => $success,
            'method' => $method,
            'identifier' => $identifier,
            'message' => $message,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
