<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $truck_id
 * @property Carbon $done_on
 * @property string $description
 * @property int $cost_kobo
 */
class MaintenanceRecord extends Model
{
    protected $fillable = ['truck_id', 'done_on', 'description', 'cost_kobo', 'workshop', 'odometer_km'];

    protected function casts(): array
    {
        return ['done_on' => 'date', 'cost_kobo' => 'integer'];
    }

    /** @return BelongsTo<Truck, $this> */
    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
