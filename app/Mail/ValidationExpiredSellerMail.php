<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Submission;

class ValidationExpiredSellerMail extends Mailable
{
    use Queueable, SerializesModels;

    public $submission;

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }

    public function build()
    {
        return $this->subject('Missed Sale Validation - ' . $this->submission->listing->title)
                    ->view('emails.validation-expired-seller')
                    ->with(['submission' => $this->submission]);
    }
}

