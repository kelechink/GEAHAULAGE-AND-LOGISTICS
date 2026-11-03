<?php

declare(strict_types=1);

namespace App\Support;

use App\Settings\WebsiteSettings;
use Illuminate\Mail\Mailables\Address;

/**
 * Which GEA mailbox each email comes from. The addresses are the mailboxes in Content → Website content:
 * info (general and staff alerts), sales (quote replies), operations/logistic (operations briefing),
 * support (tracking updates) and billing (invoices). Every one must be a verified sender with the mail
 * provider (or the whole domain authenticated). MAIL_PER_MAILBOX_SENDERS=false sends everything from
 * MAIL_FROM_ADDRESS instead, e.g. while only info@ is verified.
 */
final class Senders
{
    public const NAME = 'GEA Haulage and Logistics';

    /** @param  'info'|'sales'|'operations'|'support'|'billing'  $mailbox */
    public static function from(string $mailbox, ?string $team = null): Address
    {
        if (! config('mail.per_mailbox_senders')) {
            return new Address((string) config('mail.from.address'), (string) config('mail.from.name'));
        }

        return new Address(self::address($mailbox), $team !== null ? self::NAME.' '.$team : self::NAME);
    }

    /** Replies always reach the right team, whatever address the email was sent from. */
    public static function replyTo(string $mailbox, ?string $team = null): Address
    {
        return new Address(self::address($mailbox), $team !== null ? self::NAME.' '.$team : self::NAME);
    }

    private static function address(string $mailbox): string
    {
        $w = app(WebsiteSettings::class);

        return match ($mailbox) {
            'sales' => $w->email_sales,
            'operations' => $w->email_operations,
            'support' => $w->email_support,
            'billing' => $w->email_billing,
            default => $w->email_info,
        };
    }
}
