<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Breakdown, accident, delay or checkpoint reported from the driver app (PRD 13).
 *
 * @property int $id
 * @property int $shipment_id
 * @property int $driver_id
 * @property string $type
 * @property string|null $note
 * @property string|null $photo_path
 */
class DriverIssue extends Model
{
    public const TYPES = ['breakdown' => 'Breakdown', 'accident' => 'Accident', 'delay' => 'Delay', 'checkpoint' => 'Checkpoint'];

    protected $fillable = ['shipment_id', 'driver_id', 'type', 'note', 'photo_path', 'lat', 'lng', 'client_event_id', 'occurred_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
