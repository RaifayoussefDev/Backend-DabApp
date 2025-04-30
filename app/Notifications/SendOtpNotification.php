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
            ->subject('Votre code OTP')
            ->greeting('Bonjour ' . $notifiable->first_name . ',')
            ->line('Votre code OTP est : **' . $this->otp . '**')
            ->line('Il expire dans 5 minutes.')
            ->line('Si vous n’avez pas demandé ce code, veuillez ignorer cet e-mail.');
    }
}
