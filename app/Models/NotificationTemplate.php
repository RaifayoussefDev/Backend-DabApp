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
        'title_template_ar',
        'message_template',
        'message_template_ar',
        'email_template',
        'email_template_ar',
        'sms_template',
        'sms_template_ar',
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
    public function render(array $data, string $language = 'en'): array
    {
        // Choisir le bon template selon la langue
        $titleField = $language === 'ar' ? 'title_template_ar' : 'title_template';
        $messageField = $language === 'ar' ? 'message_template_ar' : 'message_template';

        $title = $this->{$titleField} ?? $this->title_template;
        $message = $this->{$messageField} ?? $this->message_template;

        // Remplacer les placeholders
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

    public function renderEmail(array $data, string $language = 'en'): string
    {
        $templateField = $language === 'ar' ? 'email_template_ar' : 'email_template';
        $html = $this->{$templateField} ?? $this->email_template;

        if (!$html) {
            return '';
        }

        foreach ($data as $key => $value) {
            $placeholder = "{{{$key}}}";
            $html = str_replace($placeholder, $value, $html);
        }

        return $html;
    }

    public function renderSms(array $data, string $language = 'en'): string
    {
        $templateField = $language === 'ar' ? 'sms_template_ar' : 'sms_template';
        $sms = $this->{$templateField} ?? $this->sms_template;

        if (!$sms) {
            return '';
        }

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