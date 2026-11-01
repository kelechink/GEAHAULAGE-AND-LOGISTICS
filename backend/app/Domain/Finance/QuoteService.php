<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Crm\LeadPipeline;
use App\Enums\InvoiceTemplate;
use App\Enums\LeadStage;
use App\Enums\QuoteStatus;
use App\Models\Crm\Lead;
use App\Models\Finance\Invoice;
use App\Models\Finance\Quote;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Quotes / proformas (PRD 10.3). "Quoted" on the CRM pipeline links to these (PRD 9.2).
 */
final class QuoteService
{
    public function __construct(
        private readonly NumberSequence $numbers,
        private readonly InvoiceService $invoices,
        private readonly CompanySettings $company,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{description: string, quantity: string|int, unit_price_kobo: int}>  $lines
     */
    public function save(?Quote $quote, array $attributes, array $lines, ?User $by = null): Quote
    {
        if ($quote !== null && $quote->status === QuoteStatus::Converted) {
            throw new FinanceException('This quote has been turned into an invoice and can no longer change.');
        }

        return DB::transaction(function () use ($quote, $attributes, $lines, $by): Quote {
            if ($quote === null) {
                $quote = new Quote([
                    'number' => $this->numbers->next(NumberSequence::PROFORMA),
                    'issue_date' => now()->toDateString(),
                    'valid_until' => now()->addDays($this->company->quote_valid_days)->toDateString(),
                    'vat_rate_bp' => $this->invoices->defaultVatBp(),
                    'created_by' => $by?->id,
                ]);
            }
            $quote->fill($attributes)->save();

            $quote->lines()->delete();
            $calc = [];
            foreach ($lines as $i => $line) {
                $amount = Money::kobo($line['unit_price_kobo'])->timesQuantity($line['quantity'])->kobo;
                $quote->lines()->create(['description' => $line['description'], 'quantity' => (string) $line['quantity'],
                    'unit_price_kobo' => $line['unit_price_kobo'], 'amount_kobo' => $amount, 'sort' => $i]);
                $calc[] = ['quantity' => (string) $line['quantity'], 'unit_price_kobo' => $line['unit_price_kobo']];
            }

            $t = DocumentTotals::calculate($calc, $quote->discount_kobo, $quote->vat_rate_bp);
            $quote->forceFill(['subtotal_kobo' => $t->subtotal, 'vat_kobo' => $t->vat, 'total_kobo' => $t->total])->save();

            return $quote;
        });
    }

    /** Sending a quote moves its lead to "Quoted" and records the value (PRD 9.2). */
    public function markSent(Quote $quote, ?User $by = null): Quote
    {
        $quote->update(['status' => QuoteStatus::Sent]);
        if ($quote->lead !== null) {
            $quote->lead->update(['estimated_value_kobo' => $quote->total_kobo]);
            if ($quote->lead->isOpen() && $quote->lead->stage !== LeadStage::Quoted) {
                app(LeadPipeline::class)->move($quote->lead, LeadStage::Quoted, $by);
            }
        }

        return $quote;
    }

    public function convertToInvoice(Quote $quote, ?User $by = null): Invoice
    {
        if ($quote->status === QuoteStatus::Converted && $quote->invoice !== null) {
            return $quote->invoice;
        }

        return DB::transaction(function () use ($quote, $by): Invoice {
            $invoice = $this->invoices->saveDraft(null, [
                'customer_id' => $quote->customer_id,
                'quote_id' => $quote->id,
                'discount_kobo' => $quote->discount_kobo,
                'vat_rate_bp' => $quote->vat_rate_bp,
                'template' => InvoiceTemplate::CorporateClassic,
                'created_by' => $by?->id,
                'notes' => "From quote {$quote->number}.",
            ], $quote->lines()->get()->map(fn ($l): array => [
                'description' => $l->description, 'quantity' => (string) $l->quantity, 'unit_price_kobo' => $l->unit_price_kobo,
            ])->all());

            $quote->update(['status' => QuoteStatus::Converted]);

            return $invoice;
        });
    }

    public static function leadFor(Quote $quote): ?Lead
    {
        return $quote->lead;
    }
}
