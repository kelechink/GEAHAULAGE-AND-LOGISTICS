<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $truck_id
 * @property string $name
 * @property string $phone
 * @property string|null $licence_number
 * @property Carbon|null $licence_expires_on
 * @property bool $has_smartphone
 * @property bool $is_active
 * @property-read Truck|null $truck
 * @property-read User|null $user
 */
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['user_id', 'truck_id', 'name', 'phone', 'licence_number', 'licence_expires_on', 'guarantor_name',
        'guarantor_phone', 'photo_path', 'has_smartphone', 'is_active'];

    protected $attributes = ['is_active' => true, 'has_smartphone' => false];

    protected function casts(): array
    {
        return ['licence_expires_on' => 'date', 'has_smartphone' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Truck, $this> */
    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** Share of delivered trips that arrived by the end of their ETA window. */
    public function onTimeRate(): ?int
    {
        $delivered = $this->shipments()->whereNotNull('delivered_at')->get(['delivered_at', 'pickup_date', 'stage']);
        if ($delivered->isEmpty()) {
            return null;
        }
        $onTime = $delivered->filter(fn (Shipment $s): bool => $s->events()->where('to_stage', 'delayed')->doesntExist())->count();

        return intdiv($onTime * 100, $delivered->count());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
