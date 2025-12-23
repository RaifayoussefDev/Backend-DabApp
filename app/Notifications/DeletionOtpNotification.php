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
            ->subject('Account Deletion OTP / رمز التحقق لحذف الحساب')
            ->greeting('Hello / مرحباً ' . $notifiable->first_name)
            ->line('Your OTP for account deletion is / رمز التحقق الخاص بك لحذف الحساب هو: **' . $this->otp . '**')
            ->line('It will expire in 5 minutes. / تنتهي صلاحية الرمز خلال 5 دقائق.')
            ->line('If you did not request this, please ignore this email. / إذا لم تطلب هذا الرمز، يرجى تجاهل هذا البريد الإلكتروني.');
    }
}
