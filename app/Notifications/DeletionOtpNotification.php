<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class DeletionOtpNotification extends Notification
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
            ->subject('رمز التحقق لحذف الحساب / Account Deletion OTP')
            ->greeting('مرحباً / Hello ' . $notifiable->first_name)
            ->line('رمز التحقق الخاص بك لحذف الحساب هو: **' . $this->otp . '**')
            ->line('Your OTP for account deletion is: **' . $this->otp . '**')
            ->line('هذا الرمز صالح لمدة 5 دقائق.')
            ->line('It will expire in 5 minutes.')
            ->line('إن لم تكن أنت من طلب حذف الحساب، يرجى تجاهل هذا البريد.')
            ->line('If you did not request this, please ignore this email.');
    }
}
