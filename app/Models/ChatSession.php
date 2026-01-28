<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'provider_id',
        'session_price',
        'session_status',
        'started_at',
        'ended_at',
        'duration_minutes'
    ];

    protected $casts = [
        'session_price' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer'
    ];

    // Relations
    public function booking()
    {
        return $this->belongsTo(ServiceBooking::class, 'booking_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'session_id')->orderBy('created_at');
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'session_id')->latest();
    }

    public function unreadMessages($userId)
    {
        return $this->messages()->where('sender_id', '!=', $userId)->where('is_read', false);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('session_status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('session_status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('session_status', 'completed');
    }

    public function scopeExpired($query)
    {
        return $query->where('session_status', 'expired');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    // Helper Methods
    public function start()
    {
        $this->update([
            'session_status' => 'active',
            'started_at' => now()
        ]);
    }

    public function end()
    {
        $duration = $this->started_at ? now()->diffInMinutes($this->started_at) : 0;
        
        $this->update([
            'session_status' => 'completed',
            'ended_at' => now(),
            'duration_minutes' => $duration
        ]);
    }

    public function expire()
    {
        $this->update([
            'session_status' => 'expired'
        ]);
    }

    public function canSendMessage()
    {
        return $this->session_status === 'active';
    }

    public function getUnreadCount($userId)
    {
        return $this->unreadMessages($userId)->count();
    }

    public function markMessagesAsRead($userId)
    {
        $this->unreadMessages($userId)->update(['is_read' => true, 'read_at' => now()]);
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => ['en' => 'Pending Payment', 'ar' => 'في انتظار الدفع'],
            'active' => ['en' => 'Active', 'ar' => 'نشط'],
            'completed' => ['en' => 'Completed', 'ar' => 'مكتمل'],
            'expired' => ['en' => 'Expired', 'ar' => 'منتهي']
        ];

        $locale = app()->getLocale();
        return $labels[$this->session_status][$locale] ?? $this->session_status;
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => 'warning',
            'active' => 'success',
            'completed' => 'info',
            'expired' => 'danger'
        ];

        return $colors[$this->session_status] ?? 'secondary';
    }

    public function getDurationLabelAttribute()
    {
        if (!$this->duration_minutes) {
            return '-';
        }

        $locale = app()->getLocale();
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return $hours . ' ' . ($locale === 'ar' ? 'ساعة' : 'hours') . 
                   ($minutes > 0 ? ' ' . $minutes . ' ' . ($locale === 'ar' ? 'دقيقة' : 'minutes') : '');
        }

        return $minutes . ' ' . ($locale === 'ar' ? 'دقيقة' : 'minutes');
    }
}