<?php
// app/Models/NotificationTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'description',
        'title_template',
        'message_template',
        'email_template',
        'sms_template',
        'variables',
        'icon',
        'color',
        'sound',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Methods
    public function render(array $data): array
    {
        $title = $this->title_template;
        $message = $this->message_template;

        foreach ($data as $key => $value) {
            $placeholder = "{{{$key}}}";
            $title = str_replace($placeholder, $value, $title);
            $message = str_replace($placeholder, $value, $message);
        }

        return [
            'title' => $title,
            'message' => $message,
            'icon' => $this->icon,
            'color' => $this->color,
            'sound' => $this->sound,
        ];
    }

    public function renderEmail(array $data): string
    {
        if (!$this->email_template) {
            return '';
        }

        $html = $this->email_template;

        foreach ($data as $key => $value) {
            $placeholder = "{{{$key}}}";
            $html = str_replace($placeholder, $value, $html);
        }

        return $html;
    }

    public function renderSms(array $data): string
    {
        if (!$this->sms_template) {
            return '';
        }

        $sms = $this->sms_template;

        foreach ($data as $key => $value) {
            $placeholder = "{{{$key}}}";
            $sms = str_replace($placeholder, $value, $sms);
        }

        return $sms;
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
