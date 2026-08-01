<?php

namespace App\Mail;

use App\Models\TrainerEmailInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrainerEnrollmentInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TrainerEmailInvitation $invitation,
        public readonly string $reviewUrl,
    ) {}

    public function build(): self
    {
        return $this->subject('Review your trainer invitation from '.$this->invitation->gym->name)
            ->view('mail.trainer-enrollment-invitation');
    }
}
