<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\QuoteStatus;
use App\Models\Crm\Lead;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Quote / proforma (PRD 10.3). Numbered GEA-PRO-YYYY-#####.
 *
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property int|null $lead_id
 * @property QuoteStatus $status
 * @property Carbon $issue_date
 * @property Carbon $valid_until
 * @property int $subtotal_kobo
 * @property int $discount_kobo
 * @property int $vat_rate_bp
 * @property int $vat_kobo
 * @property int $total_kobo
 * @property string|null $notes
 * @property string|null $terms
 * @property-read Customer $customer
 * @property-read Lead|null $lead
 * @property-read Collection<int, DocumentLine> $lines
 */
class Quote extends Model
{
    use LogsActivity;

    protected $fillable = ['number', 'customer_id', 'lead_id', 'status', 'issue_date', 'valid_until', 'subtotal_kobo', 'discount_kobo',
        'vat_rate_bp', 'vat_kobo', 'total_kobo', 'notes', 'terms', 'created_by'];

    protected $attributes = ['status' => 'draft', 'vat_rate_bp' => 750, 'subtotal_kobo' => 0, 'discount_kobo' => 0, 'vat_kobo' => 0, 'total_kobo' => 0];

    protected function casts(): array
    {
        return ['status' => QuoteStatus::class, 'issue_date' => 'date', 'valid_until' => 'date'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return MorphMany<DocumentLine, $this> */
    public function lines(): MorphMany
    {
        return $this->morphMany(DocumentLine::class, 'document')->orderBy('sort')->orderBy('id');
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total_kobo'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
