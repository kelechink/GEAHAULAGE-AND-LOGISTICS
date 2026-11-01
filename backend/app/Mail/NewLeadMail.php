<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Crm\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLeadMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New quote request from '.$this->lead->contact->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-lead');
    }
}
