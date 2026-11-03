<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records money received (PRD 10.5): allocated to one or more invoices, partial payments allowed,
 * WHT recorded per invoice, anything left over kept as customer credit.
 */
final class PaymentService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * @param  list<array{invoice_id: int, amount_kobo: int, wht_kobo?: int}>|null  $allocations  null = oldest invoices first
     */
    public function record(
        Customer $customer,
        int $amountKobo,
        PaymentMethod $method,
        Carbon $receivedOn,
        ?string $reference = null,
        int $whtKobo = 0,
        ?array $allocations = null,
        ?User $by = null,
        ?string $notes = null,
    ): Payment {
        if ($amountKobo <= 0 && $whtKobo <= 0) {
            throw new FinanceException('Enter the amount received.');
        }

        return DB::transaction(function () use ($customer, $amountKobo, $method, $receivedOn, $reference, $whtKobo, $allocations, $by, $notes): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->create([
                'customer_id' => $customer->id, 'received_on' => $receivedOn, 'method' => $method, 'reference' => $reference,
                'amount_kobo' => $amountKobo, 'wht_kobo' => $whtKobo, 'unallocated_kobo' => 0, 'notes' => $notes, 'recorded_by' => $by?->id,
            ]);

            $allocations ??= $this->autoAllocate($customer, $amountKobo, $whtKobo);

            $usedCash = 0;
            $usedWht = 0;
            foreach ($allocations as $a) {
                $invoice = Invoice::query()->where('customer_id', $customer->id)->receivable()->lockForUpdate()->find($a['invoice_id']);
                if ($invoice === null) {
                    throw new FinanceException('Payments can only go to this customer\'s issued, unpaid invoices.');
                }
                $wht = $a['wht_kobo'] ?? 0;
                if ($invoice->balance_kobo < $a['amount_kobo'] + $wht) {
                    throw new FinanceException("{$invoice->number} only has ".Money::kobo($invoice->balance_kobo)->format().' left to pay.');
                }
                $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount_kobo' => $a['amount_kobo'], 'wht_kobo' => $wht]);
                $usedCash += $a['amount_kobo'];
                $usedWht += $wht;
                $this->invoices->refreshStatus($invoice);
            }

            if ($usedCash > $amountKobo || $usedWht > $whtKobo) {
                throw new FinanceException('The amounts given to invoices are more than the payment.');
            }

            $payment->update(['unallocated_kobo' => $amountKobo - $usedCash]);

            return $payment;
        });
    }

    /**
     * Oldest due invoice first. WHT is applied with the cash, invoice by invoice.
     *
     * @return list<array{invoice_id: int, amount_kobo: int, wht_kobo: int}>
     */
    private function autoAllocate(Customer $customer, int $cash, int $wht): array
    {
        $out = [];
        $open = Invoice::query()->where('customer_id', $customer->id)->receivable()->orderBy('due_date')->orderBy('id')->get();
        foreach ($open as $inv) {
            if ($cash <= 0 && $wht <= 0) {
                break;
            }
            $need = $inv->balance_kobo;
            $w = min($wht, $need);
            $c = min($cash, $need - $w);
            if ($w + $c > 0) {
                $out[] = ['invoice_id' => $inv->id, 'amount_kobo' => $c, 'wht_kobo' => $w];
                $cash -= $c;
                $wht -= $w;
            }
        }

        return $out;
    }

    /** Unused money a customer has paid in advance or overpaid. */
    public function creditBalance(Customer $customer): int
    {
        return (int) Payment::query()->where('customer_id', $customer->id)->sum('unallocated_kobo');
    }
}
