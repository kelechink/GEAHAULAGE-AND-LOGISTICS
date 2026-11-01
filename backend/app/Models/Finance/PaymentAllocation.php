<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $invoice_id
 * @property int $amount_kobo
 * @property int $wht_kobo
 * @property-read Payment $payment
 * @property-read Invoice $invoice
 */
class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'invoice_id', 'amount_kobo', 'wht_kobo'];

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
