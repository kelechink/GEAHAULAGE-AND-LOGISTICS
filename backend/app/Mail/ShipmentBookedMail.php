<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Tracking\EtaEstimator;
use App\Models\Shipment;
use App\Support\Senders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** PRD 11: "Shipment booked (tracking link): email to customer". Sent from the operations mailbox (logistic@). */
class ShipmentBookedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Shipment $shipment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: Senders::from('operations', 'Operations'),
            replyTo: [Senders::replyTo('support', 'Support')],
            subject: "Shipment booked: {$this->shipment->tracking_code}, {$this->shipment->origin} to {$this->shipment->destination}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.shipment-booked', with: [
            'url' => route('track.show', $this->shipment->tracking_code),
            'pickup' => $this->shipment->pickup_date?->format('l j F Y'),
            'eta' => EtaEstimator::describe(app(EtaEstimator::class)->estimate($this->shipment)),
        ]);
    }
}
