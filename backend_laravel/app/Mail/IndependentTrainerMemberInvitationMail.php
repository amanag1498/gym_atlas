<?php

namespace App\Mail;

use App\Models\IndependentTrainerMemberInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IndependentTrainerMemberInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly IndependentTrainerMemberInvitation $invitation,
        public readonly string $reviewUrl,
    ) {}

    public function build(): self
    {
        return $this->subject('Review your coaching invitation from '.$this->invitation->trainer->name)
            ->view('mail.independent-trainer-member-invitation');
    }
}
