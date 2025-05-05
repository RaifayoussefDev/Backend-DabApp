<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SendOtpNotification extends Notification
{
    use Queueable;

    protected $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your OTP Code')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('Your OTP code is: **' . $this->otp . '**')
            ->line('It will expire in 5 minutes.')
            ->line('If you did not request this code, please ignore this email.');
    }

}
