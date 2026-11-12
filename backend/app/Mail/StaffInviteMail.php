<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\Role;
use App\Models\User;
use App\Support\Senders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: Senders::from('info'), replyTo: [Senders::replyTo('info')],
            subject: 'Welcome to the GEA Logistics Platform: set up your account');
    }

    public function content(): Content
    {
        $roles = $this->user->getRoleNames()->map(static fn (string $r): string => Role::tryFrom($r)?->label() ?? $r)->implode(', ');

        return new Content(markdown: 'mail.staff-invite', with: ['roles' => $roles, 'loginUrl' => url('/admin/login'), 'expiresHours' => (int) ceil(config('auth.passwords.users.expire') / 60)]);
    }
}
