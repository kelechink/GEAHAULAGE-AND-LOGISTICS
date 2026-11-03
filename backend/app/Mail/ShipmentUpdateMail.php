<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Support\Senders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Shipment $shipment, public ShipmentEvent $event) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: Senders::from('support', 'Support'), replyTo: [Senders::replyTo('support', 'Support')],
            subject: "{$this->shipment->tracking_code}: {$this->event->to_stage?->label()}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.shipment-update', with: ['url' => route('track.show', $this->shipment->tracking_code)]);
    }
}
