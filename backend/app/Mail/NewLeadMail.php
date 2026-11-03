<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Crm\Lead;
use App\Support\Senders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLeadMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        $email = $this->lead->contact->email;

        return new Envelope(from: Senders::from('info'), replyTo: filled($email) ? [new Address((string) $email, $this->lead->contact->name)] : [],
            subject: 'New quote request from '.$this->lead->contact->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-lead');
    }
}
