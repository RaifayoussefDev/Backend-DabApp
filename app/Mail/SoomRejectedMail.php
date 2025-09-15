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

class SoomRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $submission;
    public $listing;
    public $seller;
    public $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(Submission $submission, Listing $listing, User $seller, $reason = null)
    {
        $this->submission = $submission;
        $this->listing = $listing;
        $this->seller = $seller;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SOOM Rejected - ' . $this->listing->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.soom-rejected',
            with: [
                'submission' => $this->submission,
                'listing' => $this->listing,
                'seller' => $this->seller,
                'reason' => $this->reason,
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
