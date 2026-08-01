<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransactionalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param list<string> $lines */
    public function __construct(
        public readonly string $heading,
        public readonly string $intro,
        public readonly array $lines = [],
        public readonly array $context = [],
    ) {}

    public function build(): self
    {
        return $this->subject($this->heading)->view('mail.transactional-notification');
    }
}
