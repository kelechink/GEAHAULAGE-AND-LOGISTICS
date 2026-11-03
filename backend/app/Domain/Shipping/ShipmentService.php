<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Domain\Tracking\Geocoder;
use App\Domain\Tracking\TrackingCode;
use App\Enums\Permission;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Enums\TruckStatus;
use App\Events\ShipmentStageChanged;
use App\Mail\ShipmentBookedMail;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Creates shipments, assigns trucks and drivers, and runs the stage machine (PRD 8.2, 10.1).
 */
final class ShipmentService
{
    public function __construct(private readonly Geocoder $geocoder) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $by = null, bool $notifyCustomer = true): Shipment
    {
        $shipment = DB::transaction(function () use ($data, $by): Shipment {
            foreach (['origin', 'destination'] as $end) {
                if (empty($data["{$end}_lat"]) && filled($data[$end] ?? null)) {
                    $hit = $this->geocoder->find((string) $data[$end]);
                    $data["{$end}_lat"] = $hit['lat'] ?? null;
                    $data["{$end}_lng"] = $hit['lng'] ?? null;
                }
            }

            $shipment = Shipment::query()->create($data + [
                'tracking_code' => TrackingCode::generate(),
                'stage' => ShipmentStage::Booked,
                'created_by' => $by?->id,
            ]);

            $this->logEvent($shipment, null, ShipmentStage::Booked, TrackingSource::System, $by, 'Shipment booked.');

            if (! empty($data['truck_id'])) {
                $shipment->stage = ShipmentStage::Booked;
                $this->assign($shipment, Truck::query()->findOrFail($data['truck_id']), isset($data['driver_id']) ? Driver::query()->find($data['driver_id']) : null, $by);
            }

            return $shipment->refresh();
        });

        // PRD 11: the customer gets the tracking link by email as soon as the shipment is booked.
        if ($notifyCustomer) {
            $this->emailTrackingLink($shipment);
        }

        return $shipment;
    }

    public function emailTrackingLink(Shipment $shipment): bool
    {
        $email = $shipment->customer->email;
        if (blank($email)) {
            return false;
        }
        Mail::to((string) $email)->queue(new ShipmentBookedMail($shipment));

        return true;
    }

    /**
     * Trucks that can take this load: available and big enough (PRD 10.1).
     *
     * @return Collection<int, Truck>
     */
    public function eligibleTrucks(?Shipment $shipment = null, ?float $tonnage = null)
    {
        $tonnage ??= $shipment?->tonnage !== null ? (float) $shipment->tonnage : 0.0;

        return Truck::query()
            ->where(fn ($q) => $q->where('status', TruckStatus::Available->value)
                ->when($shipment?->truck_id !== null, fn ($q2) => $q2->orWhere('id', $shipment?->truck_id)))
            ->where('capacity_tonnes', '>=', $tonnage)
            ->orderBy('capacity_tonnes')
            ->get();
    }

    public function assign(Shipment $shipment, Truck $truck, ?Driver $driver = null, ?User $by = null): Shipment
    {
        if ($shipment->tonnage !== null && (float) $truck->capacity_tonnes < (float) $shipment->tonnage) {
            throw new ShippingException("{$truck->registration} carries {$truck->capacity_tonnes} t; this load is {$shipment->tonnage} t.");
        }
        if ($truck->status !== TruckStatus::Available && $shipment->truck_id !== $truck->id) {
            throw new ShippingException("{$truck->registration} is not available ({$truck->status->label()}).");
        }

        return DB::transaction(function () use ($shipment, $truck, $driver, $by): Shipment {
            if ($shipment->truck_id !== null && $shipment->truck_id !== $truck->id) {
                Truck::query()->whereKey($shipment->truck_id)->update(['status' => TruckStatus::Available->value]);
            }

            $driver ??= $truck->loadMissing('driver')->driver;
            $shipment->truck()->associate($truck);
            $shipment->driver()->associate($driver);
            $shipment->save();

            if ($shipment->stage === ShipmentStage::Booked) {
                $this->transition($shipment, ShipmentStage::Assigned, TrackingSource::Manual, $by,
                    "Truck {$truck->maskedRegistration()} assigned.");
            } else {
                $truck->update(['status' => TruckStatus::OnTrip->value]);
            }

            return $shipment;
        });
    }

    /**
     * @param  array{lat?: float|null, lng?: float|null, label?: string|null}  $location
     * @param  array{receiver_name: string, photo_path?: string|null, signature_path?: string|null, document_path?: string|null}|null  $proof
     */
    public function transition(
        Shipment $shipment,
        ShipmentStage $to,
        TrackingSource $source,
        ?User $by = null,
        ?string $note = null,
        array $location = [],
        bool $public = true,
        ?array $proof = null,
        ?string $clientEventId = null,
    ): ShipmentEvent {
        $from = $shipment->stage;

        if (! $from->canMoveTo($to)) {
            throw new ShippingException("A shipment can't go from {$from->label()} to {$to->label()}.");
        }
        if ($to === ShipmentStage::Cancelled) {
            if ($by === null || ! ($by->can(Permission::ShipmentsManage->value) || $by->can(Permission::InvoicesApproveVoid->value))) {
                throw new ShippingException('Only Operations or Management can cancel a shipment.');
            }
            if (blank($note)) {
                throw new ShippingException('Give a reason for cancelling.');
            }
        }
        if ($to === ShipmentStage::Delayed && blank($note)) {
            throw new ShippingException('Give a reason for the delay.');
        }
        if (in_array($to, [ShipmentStage::Assigned, ShipmentStage::Loading], true) && $shipment->truck_id === null) {
            throw new ShippingException('Assign a truck first.');
        }
        if ($to === ShipmentStage::Delivered && $proof === null && $shipment->proofs()->doesntExist()) {
            throw new ShippingException('Delivery needs proof: a photo or signature from the app, or a scan of the signed waybill.');
        }

        return DB::transaction(function () use ($shipment, $from, $to, $source, $by, $note, $location, $public, $proof, $clientEventId): ShipmentEvent {
            if ($proof !== null) {
                $shipment->proofs()->create($proof + ['source' => $source->value, 'user_id' => $by?->id]);
            }

            $shipment->stage = $to;
            $shipment->last_update_at = now();
            $shipment->stale_alerted_at = null;
            match ($to) {
                ShipmentStage::Delayed => $shipment->delay_reason = $note,
                ShipmentStage::Cancelled => $shipment->cancel_reason = $note,
                ShipmentStage::Delivered => $shipment->delivered_at = now(),
                default => null,
            };
            $shipment->save();

            if ($shipment->truck_id !== null) {
                $status = $to->occupiesTruck() ? TruckStatus::OnTrip : TruckStatus::Available;
                Truck::query()->whereKey($shipment->truck_id)->update(['status' => $status->value]);
            }

            $event = $this->logEvent($shipment, $from, $to, $source, $by, $note, $location, $public, $clientEventId);

            ShipmentStageChanged::dispatch($shipment, $event);

            return $event;
        });
    }

    /**
     * @param  array{lat?: float|null, lng?: float|null, label?: string|null}  $location
     */
    public function logEvent(
        Shipment $shipment,
        ?ShipmentStage $from,
        ?ShipmentStage $to,
        TrackingSource $source,
        ?User $by,
        ?string $note,
        array $location = [],
        bool $public = true,
        ?string $clientEventId = null,
    ): ShipmentEvent {
        /** @var ShipmentEvent $event */
        $event = $shipment->events()->create([
            'from_stage' => $from?->value,
            'to_stage' => $to?->value,
            'source' => $source->value,
            'user_id' => $by?->id,
            'lat' => $location['lat'] ?? null,
            'lng' => $location['lng'] ?? null,
            'place_label' => $location['label'] ?? null,
            'note' => $note,
            'is_public' => $public,
            'client_event_id' => $clientEventId,
            'occurred_at' => now(),
        ]);

        return $event;
    }
}
