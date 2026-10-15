<?php

namespace App\Mail;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * A new message from the website's "Contact us" form, sent to the team (CONTACT_RECEIVER).
 * Reply-To is the customer, so the team can answer straight from their inbox.
 */
class ContactSubmissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ContactSubmission $submission) {}

    public function build(): static
    {
        return $this->subject('رسالة تواصل جديدة — ' . $this->submission->name)
                    ->replyTo($this->submission->email, $this->submission->name)
                    ->view('emails.contact-submission');
    }
}
