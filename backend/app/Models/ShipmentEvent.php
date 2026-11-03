<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shipment_id
 * @property ShipmentStage|null $from_stage
 * @property ShipmentStage|null $to_stage
 * @property TrackingSource $source
 * @property string|null $place_label
 * @property string|null $note
 * @property bool $is_public
 * @property Carbon $occurred_at
 * @property-read User|null $user
 */
class ShipmentEvent extends Model
{
    protected $fillable = ['shipment_id', 'from_stage', 'to_stage', 'source', 'user_id', 'lat', 'lng', 'place_label', 'note',
        'is_public', 'client_event_id', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'from_stage' => ShipmentStage::class,
            'to_stage' => ShipmentStage::class,
            'source' => TrackingSource::class,
            'is_public' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
