<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShipmentStage;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $tracking_code
 * @property int $customer_id
 * @property int|null $lead_id
 * @property int|null $truck_id
 * @property int|null $driver_id
 * @property string $cargo_description
 * @property string|null $tonnage
 * @property string $origin
 * @property string|null $origin_lat
 * @property string|null $origin_lng
 * @property string $destination
 * @property string|null $destination_lat
 * @property string|null $destination_lng
 * @property list<array{lat: float, lng: float, label?: string}>|null $waypoints
 * @property Carbon|null $pickup_date
 * @property string|null $waybill_number
 * @property ShipmentStage $stage
 * @property string|null $delay_reason
 * @property string|null $cancel_reason
 * @property string|null $notes
 * @property Carbon|null $last_update_at
 * @property Carbon|null $stale_alerted_at
 * @property Carbon|null $delivered_at
 * @property-read Customer $customer
 * @property-read Truck|null $truck
 * @property-read Driver|null $driver
 */
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['tracking_code', 'customer_id', 'lead_id', 'truck_id', 'driver_id', 'cargo_description', 'tonnage',
        'origin', 'origin_lat', 'origin_lng', 'destination', 'destination_lat', 'destination_lng', 'waypoints', 'pickup_date',
        'waybill_number', 'stage', 'delay_reason', 'cancel_reason', 'notes', 'last_update_at', 'stale_alerted_at', 'delivered_at', 'created_by'];

    protected $attributes = ['stage' => 'booked'];

    protected function casts(): array
    {
        return [
            'stage' => ShipmentStage::class,
            'waypoints' => 'array',
            'pickup_date' => 'date',
            'last_update_at' => 'datetime',
            'stale_alerted_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Truck, $this> */
    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return HasMany<ShipmentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class)->orderBy('recorded_at');
    }

    /** @return HasOne<Position, $this> */
    public function latestPosition(): HasOne
    {
        return $this->hasOne(Position::class)->latestOfMany('recorded_at');
    }

    /** @return HasMany<ProofOfDelivery, $this> */
    public function proofs(): HasMany
    {
        return $this->hasMany(ProofOfDelivery::class);
    }

    /** @return HasMany<TrackingSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TrackingSubscription::class);
    }

    /** @param Builder<Shipment> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('stage', ShipmentStage::activeValues());
    }

    public function isStale(): bool
    {
        return $this->stage->isMoving() && ($this->last_update_at === null || $this->last_update_at->lt(now()->subMinutes(60)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['stage', 'truck_id', 'driver_id', 'customer_id', 'origin', 'destination', 'cancel_reason', 'delay_reason'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
