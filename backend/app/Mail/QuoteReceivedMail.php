<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Senders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: Senders::from('sales', 'Sales'), replyTo: [Senders::replyTo('sales', 'Sales')], subject: 'We received your quote request');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.quote-received');
    }
}
