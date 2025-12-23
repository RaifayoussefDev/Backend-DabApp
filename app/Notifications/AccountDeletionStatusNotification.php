<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionStatusNotification extends Notification
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
            ->subject('Account Deleted / تم حذف الحساب')
            ->greeting('Hello / مرحباً ' . $notifiable->first_name)
            ->line('Your account has been deleted successfully. / تم حذف حسابك بنجاح.')
            ->line('You have 30 days to reactivate your account. If you log in within this period, your account will be automatically restored. / لديك 30 يوماً لإعادة تفعيل حسابك. إذا قمت بتسجيل الدخول خلال هذه الفترة، فسيتم استعادة حسابك تلقائياً.')
            ->line('After 30 days, your account and all associated data will be permanently deleted. / بعد 30 يوماً، سيتم حذف حسابك وجميع البيانات المرتبطة به نهائياً.')
            ->line('Thank you for being with us. / شكراً لتواجدك معنا.');
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
