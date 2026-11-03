<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Support\Senders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $link, public string $company) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: Senders::from('support', 'Support'), replyTo: [Senders::replyTo('support', 'Support')],
            subject: 'Your GEA customer portal account');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.portal-invite');
    }
}
