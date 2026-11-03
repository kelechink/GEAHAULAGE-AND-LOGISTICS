<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $number
 * @property int $invoice_id
 * @property int $amount_kobo
 * @property string $reason
 * @property Carbon $issued_on
 */
class CreditNote extends Model
{
    use LogsActivity;

    protected $fillable = ['number', 'invoice_id', 'amount_kobo', 'reason', 'issued_on', 'created_by'];

    protected function casts(): array
    {
        return ['issued_on' => 'date'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
