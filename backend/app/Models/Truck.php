<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TruckStatus;
use Database\Factories\TruckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $registration
 * @property string $type
 * @property string $capacity_tonnes
 * @property string|null $make
 * @property string|null $model
 * @property int|null $year
 * @property string|null $tracker_device_id
 * @property TruckStatus $status
 * @property Carbon|null $insurance_expires_on
 * @property Carbon|null $roadworthiness_expires_on
 * @property Carbon|null $licence_expires_on
 */
class Truck extends Model
{
    /** @use HasFactory<TruckFactory> */
    use HasFactory, LogsActivity;

    public const TYPES = [
        'box_body' => 'Box body',
        'flatbed' => 'Flatbed',
        'lowbed' => 'Lowbed',
        'tanker' => 'Tanker',
        'tipper' => 'Tipper',
        'curtainsider' => 'Curtainsider',
        'container' => 'Container carrier',
    ];

    protected $fillable = ['registration', 'type', 'capacity_tonnes', 'make', 'model', 'year', 'tracker_device_id', 'status',
        'insurance_expires_on', 'roadworthiness_expires_on', 'licence_expires_on', 'notes'];

    protected $attributes = ['status' => 'available'];

    protected function casts(): array
    {
        return [
            'status' => TruckStatus::class,
            'capacity_tonnes' => 'decimal:1',
            'insurance_expires_on' => 'date',
            'roadworthiness_expires_on' => 'date',
            'licence_expires_on' => 'date',
        ];
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasMany<MaintenanceRecord, $this> */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /** @return HasOne<Driver, $this> */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /** The shipment this truck is currently committed to, if any. */
    public function activeShipment(): ?Shipment
    {
        return $this->shipments()->whereIn('stage', ['assigned', 'loading', 'loaded', 'in_transit', 'delayed', 'arrived'])->latest('id')->first();
    }

    /** "LAG-***-XY": customers never see a full plate (PRD 8.5). */
    public function maskedRegistration(): string
    {
        $parts = preg_split('/[\s-]+/', strtoupper($this->registration)) ?: [];
        if (count($parts) >= 3) {
            return $parts[0].'-***-'.substr((string) end($parts), -2);
        }

        return substr($this->registration, 0, 3).'-***-'.substr($this->registration, -2);
    }

    /**
     * Documents expiring within $days (PRD 10.2 alerts at 30, 14 and 7 days).
     *
     * @return list<array{document: string, expires_on: Carbon}>
     */
    public function expiringDocuments(int $days = 30): array
    {
        $out = [];
        foreach (['insurance_expires_on' => 'Insurance', 'roadworthiness_expires_on' => 'Roadworthiness', 'licence_expires_on' => 'Vehicle licence'] as $col => $label) {
            $date = $this->{$col};
            if ($date instanceof Carbon && $date->lte(now()->addDays($days))) {
                $out[] = ['document' => $label, 'expires_on' => $date];
            }
        }

        return $out;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
