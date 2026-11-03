<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Finance\Documents\DocumentRenderer;
use App\Models\Finance\Invoice;
use App\Settings\WebsiteSettings;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Invoice delivery and payment reminders with the PDF attached (PRD 10.3). Replies go to billing@. */
class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param 'issued'|'due_soon'|'due_today'|'overdue' $kind */
    public function __construct(public Invoice $invoice, public string $kind = 'issued') {}

    public function envelope(): Envelope
    {
        $n = $this->invoice->displayNumber();

        return new Envelope(
            // Sent from a sender verified with the mail provider (MAIL_FROM_ADDRESS, e.g. info@) unless MAIL_BILLING_FROM
            // is set; replies always go to the billing mailbox.
            from: new Address((string) (config('mail.billing_from') ?: config('mail.from.address')), (string) config('mail.from.name')),
            replyTo: [new Address(app(WebsiteSettings::class)->email_billing, 'GEA Accounts')],
            subject: match ($this->kind) {
                'due_soon' => "Reminder: invoice {$n} is due in 3 days",
                'due_today' => "Invoice {$n} is due today",
                'overdue' => "Overdue: invoice {$n}",
                default => "Invoice {$n} from GEA Haulage and Logistics",
            },
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice', with: [
            'balance' => Money::kobo($this->invoice->balance_kobo)->format(),
            'total' => Money::kobo($this->invoice->total_kobo)->format(),
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $renderer = app(DocumentRenderer::class);

        return [Attachment::fromData(fn () => $renderer->pdf($this->invoice), $renderer->filename($this->invoice, 'pdf'))->withMime('application/pdf')];
    }
}
