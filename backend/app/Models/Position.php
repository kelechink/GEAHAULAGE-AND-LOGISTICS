<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrackingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $shipment_id
 * @property int|null $truck_id
 * @property TrackingSource $source
 * @property string $lat
 * @property string $lng
 * @property int|null $speed_kmh
 * @property string|null $place_label
 * @property Carbon $recorded_at
 * @property Carbon $received_at
 */
class Position extends Model
{
    public $timestamps = false;

    protected $fillable = ['shipment_id', 'truck_id', 'source', 'lat', 'lng', 'accuracy_m', 'speed_kmh', 'heading', 'place_label',
        'client_event_id', 'recorded_at', 'received_at'];

    protected function casts(): array
    {
        return ['source' => TrackingSource::class, 'recorded_at' => 'datetime', 'received_at' => 'datetime'];
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
