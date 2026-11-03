<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Ai\DailyDigest;
use App\Models\Ai\Digest;
use App\Support\Senders;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Digest $digest, public bool $withMoney) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: Senders::from('operations', 'Operations'), subject: 'GEA morning briefing, '.$this->digest->day->format('l j M'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.daily-digest', with: [
            'summary' => $this->digest->summary,
            'facts' => $this->digest->facts,
            'money' => $this->withMoney ? DailyDigest::money($this->digest->facts) : null,
            'url' => url('/admin'),
        ]);
    }
}
