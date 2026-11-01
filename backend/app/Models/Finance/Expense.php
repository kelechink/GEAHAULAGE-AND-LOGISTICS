<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\ExpenseCategory;
use App\Models\Shipment;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property ExpenseCategory $category
 * @property Carbon $spent_on
 * @property int $amount_kobo
 * @property string $description
 * @property string|null $vendor
 * @property int|null $truck_id
 * @property int|null $shipment_id
 * @property int|null $maintenance_record_id
 * @property string|null $receipt_path
 * @property-read Truck|null $truck
 * @property-read Shipment|null $shipment
 */
class Expense extends Model
{
    use LogsActivity;

    protected $fillable = ['category', 'spent_on', 'amount_kobo', 'description', 'vendor', 'truck_id', 'shipment_id',
        'maintenance_record_id', 'receipt_path', 'recorded_by'];

    protected function casts(): array
    {
        return ['category' => ExpenseCategory::class, 'spent_on' => 'date', 'amount_kobo' => 'integer'];
    }

    /** @return BelongsTo<Truck, $this> */
    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
