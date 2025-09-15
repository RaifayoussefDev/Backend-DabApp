<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Submission;
use App\Models\Listing;
use App\Models\User;

class SoomAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $submission;
    public $listing;
    public $seller;

    /**
     * Create a new message instance.
     */
    public function __construct(Submission $submission, Listing $listing, User $seller)
    {
        $this->submission = $submission;
        $this->listing = $listing;
        $this->seller = $seller;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SOOM Accepted - ' . $this->listing->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.soom-accepted',
            with: [
                'submission' => $this->submission,
                'listing' => $this->listing,
                'seller' => $this->seller,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
