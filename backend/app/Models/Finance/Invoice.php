<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string|null $number
 * @property int $customer_id
 * @property int|null $shipment_id
 * @property int|null $quote_id
 * @property InvoiceStatus $status
 * @property InvoiceTemplate $template
 * @property Carbon|null $issue_date
 * @property Carbon|null $due_date
 * @property int $subtotal_kobo
 * @property int $discount_kobo
 * @property int $vat_rate_bp
 * @property int $vat_kobo
 * @property int $total_kobo
 * @property int $amount_paid_kobo
 * @property int $wht_kobo
 * @property int $credited_kobo
 * @property int $balance_kobo
 * @property string|null $supplier_tin
 * @property string|null $supplier_vat_number
 * @property string|null $buyer_tin
 * @property string|null $irn
 * @property string|null $qr_payload
 * @property string|null $stamp
 * @property string|null $einvoice_status
 * @property string|null $notes
 * @property string|null $void_reason
 * @property Carbon|null $voided_at
 * @property Carbon|null $last_reminder_at
 * @property-read Customer $customer
 * @property-read Shipment|null $shipment
 * @property-read Collection<int, DocumentLine> $lines
 */
class Invoice extends Model
{
    use LogsActivity;

    protected $fillable = ['number', 'customer_id', 'shipment_id', 'quote_id', 'status', 'template', 'issue_date', 'due_date',
        'subtotal_kobo', 'discount_kobo', 'vat_rate_bp', 'vat_kobo', 'total_kobo', 'amount_paid_kobo', 'wht_kobo', 'credited_kobo',
        'balance_kobo', 'supplier_tin', 'supplier_vat_number', 'buyer_tin', 'irn', 'qr_payload', 'stamp', 'einvoice_status',
        'notes', 'void_reason', 'voided_at', 'voided_by', 'last_reminder_at', 'created_by'];

    protected $attributes = ['status' => 'draft', 'template' => 'haulage_trip', 'vat_rate_bp' => 750, 'subtotal_kobo' => 0,
        'discount_kobo' => 0, 'vat_kobo' => 0, 'total_kobo' => 0, 'amount_paid_kobo' => 0, 'wht_kobo' => 0, 'credited_kobo' => 0, 'balance_kobo' => 0];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'template' => InvoiceTemplate::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'voided_at' => 'datetime',
            'last_reminder_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<DocumentLine, $this> */
    public function lines(): MorphMany
    {
        return $this->morphMany(DocumentLine::class, 'document')->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasMany<CreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /** @param Builder<Invoice> $query */
    public function scopeReceivable(Builder $query): void
    {
        $query->whereIn('status', ['issued', 'partially_paid', 'overdue']);
    }

    public function displayNumber(): string
    {
        return $this->number ?? 'Draft #'.$this->id;
    }

    /** Issued without an IRN while e-invoicing is required (PRD 10.3). */
    public function needsEInvoice(bool $required): bool
    {
        return $required && $this->status !== InvoiceStatus::Draft && $this->status !== InvoiceStatus::Void && blank($this->irn);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['number', 'status', 'total_kobo', 'amount_paid_kobo', 'wht_kobo', 'balance_kobo', 'void_reason', 'due_date'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
