<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'sender_id',
        'sender_type',
        'message',
        'message_type',
        'attachment_url',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime'
    ];

    // Relations
    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeText($query)
    {
        return $query->where('message_type', 'text');
    }

    public function scopeWithAttachment($query)
    {
        return $query->whereIn('message_type', ['image', 'file']);
    }

    public function scopeBySender($query, $senderId)
    {
        return $query->where('sender_id', $senderId);
    }

    // Helper Methods
    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }
    }

    public function isSentByUser()
    {
        return $this->sender_type === 'user';
    }

    public function isSentByProvider()
    {
        return $this->sender_type === 'provider';
    }

    // Accessors
    public function getAttachmentFullUrlAttribute()
    {
        return $this->attachment_url ? asset('storage/' . $this->attachment_url) : null;
    }

    public function getTimestampLabelAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getMessageTypeLabelAttribute()
    {
        $labels = [
            'text' => ['en' => 'Text', 'ar' => 'نص'],
            'image' => ['en' => 'Image', 'ar' => 'صورة'],
            'file' => ['en' => 'File', 'ar' => 'ملف']
        ];

        $locale = app()->getLocale();
        return $labels[$this->message_type][$locale] ?? $this->message_type;
    }

    public function getSenderNameAttribute()
    {
        return $this->sender ? $this->sender->full_name : 'Unknown';
    }
}