<?php
// app/Mail/NotificationMail.php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log; // ← AJOUTER CET IMPORT!

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $notification;
    public $emailContent;
    public $data;
    public $primaryColor = '#f03d24';
    public $secondaryColor = '#032c40';
    public $logoBase64;

    public function __construct(Notification $notification, string $emailContent = null, array $data = [])
    {
        $this->notification = $notification;
        $this->emailContent = $emailContent ?? $notification->message;
        $this->data = $data;

        // Encoder le logo en base64
        $this->logoBase64 = $this->getLogoBase64();
    }

    protected function getLogoBase64(): string
    {
        try {
            // Télécharger le SVG depuis ton site
            $svgContent = @file_get_contents('https://dabapp.co/assets/images/logo-header.svg');

            if ($svgContent) {
                // Encoder en base64
                return 'data:image/svg+xml;base64,' . base64_encode($svgContent);
            }
        } catch (\Exception $e) {
            Log::error('Logo encoding error: ' . $e->getMessage()); // ← Log au lieu de \Log
        }

        // Fallback: retourner l'URL directe
        return 'https://dabapp.co/assets/images/logo-header.svg';
    }

    public function build()
    {
        return $this->subject($this->notification->title)
                    ->view('emails.notification')
                    ->with([
                        'notification' => $this->notification,
                        'emailContent' => $this->emailContent,
                        'data' => $this->data,
                        'primaryColor' => $this->primaryColor,
                        'secondaryColor' => $this->secondaryColor,
                        'logoBase64' => $this->logoBase64,
                        'actionUrl' => $this->notification->action_url,
                        'userName' => $this->notification->user->first_name ?? $this->notification->user->email,
                    ]);
    }
}
