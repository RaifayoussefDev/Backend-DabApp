<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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

    public function __construct(Submission $submission, Listing $listing, User $seller, $reason = null)
    {
        $this->submission = $submission;
        $this->listing = $listing;
        $this->seller = $seller;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('SOOM مرفوض / SOOM Rejected - ' . $this->listing->title)
                    ->view('emails.soom-rejected');
    }
}
