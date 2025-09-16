<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\AuctionHistory;
use App\Models\Submission;
use App\Models\User;

class SaleValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $auctionHistory;
    public $submission;
    public $seller;
    public $buyer;

    /**
     * Create a new message instance.
     */
    public function __construct(AuctionHistory $auctionHistory, Submission $submission, User $seller, User $buyer)
    {
        $this->auctionHistory = $auctionHistory;
        $this->submission = $submission;
        $this->seller = $seller;
        $this->buyer = $buyer;
    }

    /**
     * Get the message envelope.
     */
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'البيع مؤكد / Sale Validated - ' . $this->submission->listing->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.sale-validated-auction',
            with: [
                'auctionHistory' => $this->auctionHistory,
                'submission' => $this->submission,
                'seller' => $this->seller,
                'buyer' => $this->buyer,
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
