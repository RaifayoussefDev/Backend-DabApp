<?php

// app/Mail/SoomCreatedMail.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Submission;
use App\Models\Listing;
use App\Models\User;

class SoomCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $submission;
    public $listing;
    public $buyer;

    public function __construct(Submission $submission, Listing $listing, User $buyer)
    {
        $this->submission = $submission;
        $this->listing = $listing;
        $this->buyer = $buyer;
    }

    public function build()
    {
        return $this->subject('SOOM جديد مُستلم / New SOOM Received - ' . $this->listing->title)
                    ->view('emails.soom-created');
    }
}
