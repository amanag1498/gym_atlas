<?php

namespace App\Mail;

use App\Models\MemberEmailInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MemberEnrollmentInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly MemberEmailInvitation $invitation, public readonly string $reviewUrl) {}

    public function build(): self
    {
        return $this->subject('Approve your gym enrollment for '.$this->invitation->gym->name)
            ->view('mail.member-enrollment-invitation');
    }
}
