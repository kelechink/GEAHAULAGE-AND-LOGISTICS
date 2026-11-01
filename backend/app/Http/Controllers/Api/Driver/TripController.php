<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Driver;

use App\Domain\Shipping\ShippingException;
use App\Domain\Tracking\DriverApp;
use App\Domain\Tracking\EtaEstimator;
use App\Enums\ShipmentStage;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverIssue;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Driver app endpoints (PRD 13). Writes carry the app's client_event_id so a resent offline batch is a no-op.
 * Trip payloads never include the price, the customer's private notes or other customers' data.
 */
class TripController extends Controller
{
    public function __construct(private readonly DriverApp $app, private readonly EtaEstimator $eta) {}

    public function index(Request $request): JsonResponse
    {
        $driver = $this->driver($request);

        return response()->json(['trips' => $this->app->trips($driver)->map(fn (Shipment $s): array => $this->present($s))->values()]);
    }

    public function show(Request $request, int $trip): JsonResponse
    {
        return $this->guarded(fn (): JsonResponse => response()->json(['trip' => $this->present($this->app->trip($this->driver($request), $trip))]));
    }

    public function positions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'positions' => ['required', 'array', 'min:1', 'max:500'],
            'positions.*.client_event_id' => ['required', 'string', 'max:64'],
            'positions.*.shipment_id' => ['required', 'integer'],
            'positions.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'positions.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'positions.*.recorded_at' => ['required', 'date'],
            'positions.*.speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:250'],
            'positions.*.accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'positions.*.heading' => ['nullable', 'numeric', 'between:0,360'],
        ]);

        return response()->json($this->app->uploadPositions($this->driver($request), array_values($data['positions'])));
    }

    public function stage(Request $request, int $trip): JsonResponse
    {
        $data = $request->validate([
            'client_event_id' => ['required', 'string', 'max:64'],
            'stage' => ['required', Rule::in(array_map(fn (ShipmentStage $s): string => $s->value, array_unique(DriverApp::DRIVER_STEPS, SORT_REGULAR)))],
            'note' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        return $this->guarded(function () use ($request, $trip, $data): JsonResponse {
            $driver = $this->driver($request);
            $s = $this->app->trip($driver, $trip);
            $event = $this->app->setStage($driver, $s, ShipmentStage::from($data['stage']), $data['client_event_id'],
                ['lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null], $data['note'] ?? null);

            return $this->eventResponse($event, $s);
        });
    }

    public function deliver(Request $request, int $trip): JsonResponse
    {
        $data = $request->validate([
            'client_event_id' => ['required', 'string', 'max:64'],
            'receiver_name' => ['required', 'string', 'max:120'],
            'photo' => ['nullable', 'image', 'max:8192'],
            'signature' => ['nullable', 'image', 'max:2048'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        return $this->guarded(function () use ($request, $trip, $data): JsonResponse {
            $driver = $this->driver($request);
            $s = $this->app->trip($driver, $trip);
            $existing = ShipmentEvent::query()->where('client_event_id', $data['client_event_id'])->first();
            if ($existing !== null) {
                return $this->eventResponse($existing, $s);
            }

            $proof = [
                'receiver_name' => $data['receiver_name'],
                'photo_path' => $this->store($request->file('photo'), $s),
                'signature_path' => $this->store($request->file('signature'), $s),
            ];
            $event = $this->app->deliver($driver, $s, $proof, $data['client_event_id'],
                ['lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null]);

            return $this->eventResponse($event, $s);
        });
    }

    public function issue(Request $request, int $trip): JsonResponse
    {
        $data = $request->validate([
            'client_event_id' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::in(array_keys(DriverIssue::TYPES))],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn (): bool => $request->input('type') === 'delay')],
            'photo' => ['nullable', 'image', 'max:8192'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        return $this->guarded(function () use ($request, $trip, $data): JsonResponse {
            $driver = $this->driver($request);
            $s = $this->app->trip($driver, $trip);
            $issue = $this->app->reportIssue($driver, $s, [
                'type' => $data['type'], 'note' => $data['note'] ?? null,
                'photo_path' => DriverIssue::query()->where('client_event_id', $data['client_event_id'])->exists() ? null : $this->store($request->file('photo'), $s),
                'lat' => isset($data['lat']) ? (float) $data['lat'] : null, 'lng' => isset($data['lng']) ? (float) $data['lng'] : null,
            ], $data['client_event_id']);

            return response()->json(['issue_id' => $issue->id, 'trip' => $this->present($s->refresh())]);
        });
    }

    private function driver(Request $request): Driver
    {
        /** @var Driver $driver */
        $driver = $request->user();

        return $driver;
    }

    /** @param  callable(): JsonResponse  $fn */
    private function guarded(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (ShippingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function eventResponse(ShipmentEvent $event, Shipment $s): JsonResponse
    {
        return response()->json(['event_id' => $event->id, 'trip' => $this->present($s->refresh())]);
    }

    private function store(?UploadedFile $file, Shipment $s): ?string
    {
        // Private disk: proof photos are only served through the admin panel.
        return $file?->store('pod/'.$s->tracking_code, 'local') ?: null;
    }

    /** @return array<string, mixed> */
    private function present(Shipment $s): array
    {
        $next = $this->app->nextStage($s);
        $eta = in_array($s->stage, [ShipmentStage::InTransit, ShipmentStage::Delayed], true) ? $this->eta->estimate($s) : null;

        return [
            'id' => $s->id,
            'tracking_code' => $s->tracking_code,
            'stage' => $s->stage->value,
            'stage_label' => $s->stage->label(),
            'next_stage' => $next?->value,
            'next_stage_label' => $next?->label(),
            'customer' => $s->customer->name,
            'cargo' => $s->cargo_description,
            'tonnage' => $s->tonnage,
            'origin' => $s->origin,
            'origin_lat' => $s->origin_lat !== null ? (float) $s->origin_lat : null,
            'origin_lng' => $s->origin_lng !== null ? (float) $s->origin_lng : null,
            'destination' => $s->destination,
            'destination_lat' => $s->destination_lat !== null ? (float) $s->destination_lat : null,
            'destination_lng' => $s->destination_lng !== null ? (float) $s->destination_lng : null,
            'pickup_date' => $s->pickup_date?->toDateString(),
            'waybill_number' => $s->waybill_number,
            'truck' => $s->truck?->registration,
            'eta' => EtaEstimator::describe($eta),
            'needs_proof' => $s->stage === ShipmentStage::Arrived,
            'delivered_at' => $s->delivered_at?->toIso8601String(),
        ];
    }
}
