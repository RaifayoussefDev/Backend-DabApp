<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssistanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $title,
        public readonly string $body,
        public readonly string $lang,
    ) {}

    public function build(): static
    {
        return $this->subject($this->title)
                    ->view('emails.assist-notification');
    }
}
