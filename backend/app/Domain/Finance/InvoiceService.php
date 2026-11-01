<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Finance\EInvoice\EInvoiceProvider;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Enums\Permission;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Shipment;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Invoice lifecycle (PRD 10.3): DRAFT → ISSUED → PARTIALLY_PAID → PAID, plus OVERDUE and VOID.
 * Nothing is ever deleted.
 */
final class InvoiceService
{
    public const DISCOUNT_APPROVAL_PERCENT = 10;

    public function __construct(
        private readonly NumberSequence $numbers,
        private readonly CompanySettings $company,
        private readonly EInvoiceProvider $einvoice,
    ) {}

    public function defaultVatBp(): int
    {
        return Money::basisPoints(rtrim(rtrim(number_format($this->company->vat_rate, 2, '.', ''), '0'), '.'));
    }

    /**
     * Creates or updates a draft and its lines, then recalculates totals.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{description: string, quantity: string|int, unit_price_kobo: int}>  $lines
     */
    public function saveDraft(?Invoice $invoice, array $attributes, array $lines): Invoice
    {
        if ($invoice !== null && $invoice->status !== InvoiceStatus::Draft) {
            throw new FinanceException('Only draft invoices can be edited. Void it or raise a credit note instead.');
        }

        return DB::transaction(function () use ($invoice, $attributes, $lines): Invoice {
            $invoice ??= new Invoice(['vat_rate_bp' => $this->defaultVatBp()]);
            $invoice->fill($attributes);
            $invoice->status = InvoiceStatus::Draft;
            $invoice->save();

            $invoice->lines()->delete();
            foreach ($lines as $i => $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => (string) $line['quantity'],
                    'unit_price_kobo' => $line['unit_price_kobo'],
                    'amount_kobo' => Money::kobo($line['unit_price_kobo'])->timesQuantity($line['quantity'])->kobo,
                    'sort' => $i,
                ]);
            }

            return $this->recalculate($invoice);
        });
    }

    public function recalculate(Invoice $invoice): Invoice
    {
        $lines = $invoice->lines()->get()->map(fn ($l): array => ['quantity' => (string) $l->quantity, 'unit_price_kobo' => $l->unit_price_kobo])->all();
        $t = DocumentTotals::calculate($lines, $invoice->discount_kobo, $invoice->vat_rate_bp);

        $invoice->forceFill([
            'subtotal_kobo' => $t->subtotal,
            'vat_kobo' => $t->vat,
            'total_kobo' => $t->total,
            'balance_kobo' => DocumentTotals::balance($t->total, $invoice->amount_paid_kobo, $invoice->wht_kobo, $invoice->credited_kobo),
        ])->save();

        return $invoice;
    }

    public function issue(Invoice $invoice, ?User $by = null): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new FinanceException('This invoice has already been issued.');
        }
        if ($invoice->lines()->doesntExist() || $invoice->total_kobo <= 0) {
            throw new FinanceException('Add at least one line with a price before issuing.');
        }
        // Large discounts need Management approval (PRD 4).
        if ($invoice->subtotal_kobo > 0 && $invoice->discount_kobo * 100 > $invoice->subtotal_kobo * self::DISCOUNT_APPROVAL_PERCENT
            && ! ($by?->can(Permission::DiscountsApprove->value) ?? false)) {
            throw new FinanceException('Discounts over '.self::DISCOUNT_APPROVAL_PERCENT.'% need Management to issue the invoice.');
        }

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->loadMissing('customer');
            $issue = now()->timezone('Africa/Lagos')->startOfDay();
            $invoice->forceFill([
                'number' => $this->numbers->next(NumberSequence::INVOICE),
                'status' => InvoiceStatus::Issued,
                'issue_date' => $issue,
                'due_date' => $invoice->due_date ?? $issue->copy()->addDays($invoice->customer->payment_terms_days),
                'supplier_tin' => $this->company->tin,
                'supplier_vat_number' => $this->company->vat_number,
                'buyer_tin' => $invoice->customer->tin,
            ])->save();

            if ($this->company->einvoicing_required || $invoice->template === InvoiceTemplate::EInvoice) {
                $result = $this->einvoice->submit($invoice);
                $invoice->forceFill(['irn' => $result['irn'], 'qr_payload' => $result['qr_payload'], 'stamp' => $result['stamp'], 'einvoice_status' => $result['status']])->save();
            }

            return $this->refreshStatus($invoice);
        });
    }

    /**
     * Applies payments, WHT and credit notes to the balance and works out the status.
     */
    public function refreshStatus(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)) {
            return $invoice;
        }

        $paid = (int) $invoice->allocations()->sum('amount_kobo');
        $wht = (int) $invoice->allocations()->sum('wht_kobo');
        $credited = (int) $invoice->creditNotes()->sum('amount_kobo');
        $balance = DocumentTotals::balance($invoice->total_kobo, $paid, $wht, $credited);

        $status = match (true) {
            $balance <= 0 => InvoiceStatus::Paid,
            $invoice->due_date !== null && $invoice->due_date->lt(now()->timezone('Africa/Lagos')->startOfDay()) => InvoiceStatus::Overdue,
            $paid + $wht + $credited > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Issued,
        };

        $invoice->forceFill(['amount_paid_kobo' => $paid, 'wht_kobo' => $wht, 'credited_kobo' => $credited, 'balance_kobo' => $balance, 'status' => $status])->save();

        return $invoice;
    }

    /**
     * Voiding never deletes. A paid (or part-paid) invoice can only be voided once a credit note
     * or refund covers the money received (PRD 19 "Void" case).
     */
    public function void(Invoice $invoice, string $reason, ?User $by = null): Invoice
    {
        if (blank($reason)) {
            throw new FinanceException('Give a reason for voiding.');
        }
        if ($invoice->status === InvoiceStatus::Void) {
            return $invoice;
        }

        $received = (int) $invoice->allocations()->sum('amount_kobo') + (int) $invoice->allocations()->sum('wht_kobo');
        $credited = (int) $invoice->creditNotes()->sum('amount_kobo');
        if ($received > 0 && $credited < $received) {
            throw new FinanceException('This invoice has payments. Issue a credit note or refund for '.Money::kobo($received - $credited)->format().' before voiding it.');
        }

        $invoice->forceFill(['status' => InvoiceStatus::Void, 'void_reason' => $reason, 'voided_at' => now(), 'voided_by' => $by?->id, 'balance_kobo' => 0])->save();

        return $invoice;
    }

    public function creditNote(Invoice $invoice, int $amountKobo, string $reason, ?User $by = null): CreditNote
    {
        if ($invoice->status === InvoiceStatus::Draft || $invoice->status === InvoiceStatus::Void) {
            throw new FinanceException('Credit notes can only be raised on issued invoices.');
        }
        $alreadyCredited = (int) $invoice->creditNotes()->sum('amount_kobo');
        if ($amountKobo <= 0 || $amountKobo + $alreadyCredited > $invoice->total_kobo) {
            throw new FinanceException('The credit note must be more than ₦0 and no more than the invoice total.');
        }

        return DB::transaction(function () use ($invoice, $amountKobo, $reason, $by): CreditNote {
            /** @var CreditNote $note */
            $note = $invoice->creditNotes()->create([
                'number' => $this->numbers->next(NumberSequence::CREDIT_NOTE),
                'amount_kobo' => $amountKobo,
                'reason' => $reason,
                'issued_on' => now()->toDateString(),
                'created_by' => $by?->id,
            ]);
            $this->refreshStatus($invoice);

            return $note;
        });
    }

    /**
     * DELIVERED automatically creates a draft for Finance (PRD 8.2), using the Haulage Trip template.
     */
    public function draftFromShipment(Shipment $shipment): Invoice
    {
        $existing = Invoice::query()->where('shipment_id', $shipment->id)->where('status', '!=', InvoiceStatus::Void->value)->first();
        if ($existing !== null) {
            return $existing;
        }

        $price = 0;
        if ($shipment->lead_id !== null) {
            $price = (int) DB::table('leads')->where('id', $shipment->lead_id)->value('estimated_value_kobo');
        }

        $truck = $shipment->truck?->registration;

        return $this->saveDraft(null, [
            'customer_id' => $shipment->customer_id,
            'shipment_id' => $shipment->id,
            'template' => InvoiceTemplate::HaulageTrip,
            'notes' => 'Waybill '.($shipment->waybill_number ?? 'not recorded').'. Check the price before issuing.',
        ], [[
            'description' => "Haulage: {$shipment->cargo_description}, {$shipment->origin} to {$shipment->destination}".($truck ? " (truck {$truck})" : '').", {$shipment->tracking_code}",
            'quantity' => '1',
            'unit_price_kobo' => $price,
        ]]);
    }

    /** Daily job: issued invoices past their due date become OVERDUE. */
    public function markOverdue(): int
    {
        $count = 0;
        Invoice::query()->whereIn('status', ['issued', 'partially_paid'])->where('due_date', '<', now()->timezone('Africa/Lagos')->startOfDay())
            ->each(function (Invoice $i) use (&$count): void {
                $this->refreshStatus($i);
                $count++;
            });

        return $count;
    }
}
