<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $customer_id
 * @property Carbon $received_on
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property int $amount_kobo
 * @property int $wht_kobo
 * @property int $unallocated_kobo
 * @property-read Customer $customer
 */
class Payment extends Model
{
    use LogsActivity;

    protected $fillable = ['customer_id', 'received_on', 'method', 'reference', 'amount_kobo', 'wht_kobo', 'unallocated_kobo', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'method' => PaymentMethod::class];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
