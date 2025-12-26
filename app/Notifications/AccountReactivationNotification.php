<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountReactivationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Reactivated / تم تفعيل الحساب')
            ->greeting('Hello / مرحباً ' . $notifiable->first_name)
            ->line('Your account has been successfully reactivated. / تم إعادة تفعيل حسابك بنجاح.')
            ->line('Welcome back! / أهلاً بعودتك!')
            ->action('Go to App / الذهاب للتطبيق', url('/'))
            ->line('Thank you for using our application! / شكراً لاستخدامك تطبيقنا!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }
}
