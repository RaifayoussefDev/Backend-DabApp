<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
{
    use Queueable;

    private $resetCode;

    /**
     * Create a new notification instance.
     */
    public function __construct($resetCode)
    {
        $this->resetCode = $resetCode;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * 🔥 CORRECTION: Utiliser la même structure que SendOtpNotification
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Password Reset Code - dabapp.co')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('You have requested to reset your password.')
            ->line('Your password reset code is: **' . $this->resetCode . '**')
            ->line('This code will expire in 15 minutes.')
            ->line('If you did not request a password reset, please ignore this email.')
            ->line('For security reasons, please do not share this code with anyone.')
            ->salutation('Best regards, The dabapp.co Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reset_code' => $this->resetCode,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}