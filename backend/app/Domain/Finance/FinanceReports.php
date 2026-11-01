<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Enums\ExpenseCategory;
use App\Enums\InvoiceStatus;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PRD 10.5 reports. Revenue is recognised on issued invoices (net of VAT); cash on payments.
 * All sums are integer kobo.
 */
final class FinanceReports
{
    /** @return Collection<int, Invoice> */
    private function invoices(Carbon $from, Carbon $to): Collection
    {
        return Invoice::query()->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Void->value])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->with(['customer', 'shipment.truck'])
            ->get();
    }

    /** @return list<array{label: string, revenue: int, count: int}> */
    public function revenueBy(string $dimension, Carbon $from, Carbon $to): array
    {
        $groups = $this->invoices($from, $to)->groupBy(fn (Invoice $i): string => match ($dimension) {
            'customer' => $i->customer->name,
            'route' => $i->shipment !== null ? "{$i->shipment->origin} → {$i->shipment->destination}" : 'No trip',
            'truck' => $i->shipment->truck->registration ?? 'No truck',
            default => $i->issue_date?->format('Y-m') ?? '',
        });

        return $groups->map(fn (Collection $g, string $k): array => [
            'label' => $k, 'revenue' => (int) $g->sum(fn (Invoice $i) => $i->total_kobo - $i->vat_kobo), 'count' => $g->count(),
        ])->sortByDesc('revenue')->values()->all();
    }

    /** @return list<array{label: string, amount: int}> */
    public function expensesBy(string $dimension, Carbon $from, Carbon $to): array
    {
        return Expense::query()->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->with('truck')->get()
            ->groupBy(fn (Expense $e): string => $dimension === 'truck' ? ($e->truck->registration ?? 'Not linked to a truck') : $e->category->label())
            ->map(fn (Collection $g, string $k): array => ['label' => $k, 'amount' => (int) $g->sum('amount_kobo')])
            ->sortByDesc('amount')->values()->all();
    }

    /**
     * Profit per trip or per truck: invoiced revenue (net of VAT) minus linked expenses.
     *
     * @return list<array{label: string, revenue: int, expenses: int, profit: int}>
     */
    public function profitBy(string $dimension, Carbon $from, Carbon $to): array
    {
        $rows = [];
        foreach ($this->invoices($from, $to) as $i) {
            $key = $dimension === 'truck' ? ($i->shipment->truck->registration ?? null) : ($i->shipment->tracking_code ?? null);
            if ($key === null) {
                continue;
            }
            $rows[$key]['revenue'] = ($rows[$key]['revenue'] ?? 0) + $i->total_kobo - $i->vat_kobo;
        }
        $expenses = Expense::query()->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->with(['truck', 'shipment'])->get();
        foreach ($expenses as $e) {
            $key = $dimension === 'truck' ? $e->truck?->registration : $e->shipment?->tracking_code;
            if ($key === null) {
                continue;
            }
            $rows[$key]['expenses'] = ($rows[$key]['expenses'] ?? 0) + $e->amount_kobo;
        }

        $out = [];
        foreach ($rows as $k => $r) {
            $rev = $r['revenue'] ?? 0;
            $exp = $r['expenses'] ?? 0;
            $out[] = ['label' => (string) $k, 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
        }
        usort($out, fn ($a, $b) => $b['profit'] <=> $a['profit']);

        return $out;
    }

    /**
     * Receivables ageing by days past due: 0–30, 31–60, 61–90, 90+ (PRD 10.5).
     *
     * @return array{buckets: array<string, int>, rows: list<array{customer: string, number: string, due: string, days: int, balance: int}>}
     */
    public function ageing(?Carbon $asOf = null): array
    {
        $asOf ??= now()->timezone('Africa/Lagos')->startOfDay();
        $buckets = ['0–30' => 0, '31–60' => 0, '61–90' => 0, '90+' => 0];
        $rows = [];
        foreach (Invoice::query()->receivable()->with('customer')->orderBy('due_date')->get() as $i) {
            $days = $i->due_date !== null && $i->due_date->lt($asOf) ? (int) $i->due_date->diffInDays($asOf) : 0;
            $bucket = match (true) {
                $days <= 30 => '0–30', $days <= 60 => '31–60', $days <= 90 => '61–90', default => '90+'
            };
            $buckets[$bucket] += $i->balance_kobo;
            $rows[] = ['customer' => $i->customer->name, 'number' => $i->displayNumber(), 'due' => $i->due_date?->format('j M Y') ?? '', 'days' => $days, 'balance' => $i->balance_kobo];
        }

        return ['buckets' => $buckets, 'rows' => $rows];
    }

    /** @return array{output_vat: int, taxable: int, invoices: int} */
    public function vatSummary(Carbon $from, Carbon $to): array
    {
        $inv = $this->invoices($from, $to);

        return ['output_vat' => (int) $inv->sum('vat_kobo'), 'taxable' => (int) $inv->sum(fn (Invoice $i) => $i->subtotal_kobo - $i->discount_kobo), 'invoices' => $inv->count()];
    }

    /** WHT deducted by customers; GEA claims this back with the credit notes from FIRS. */
    public function whtReceivable(Carbon $from, Carbon $to): int
    {
        return (int) PaymentAllocation::query()->whereHas('payment', fn ($q) => $q->whereBetween('received_on', [$from->toDateString(), $to->toDateString()]))->sum('wht_kobo');
    }

    /**
     * Cash in (payments) and money out (expenses) per month, for the dashboard (task 7).
     *
     * @return list<array{month: string, label: string, income: int, expenses: int}>
     */
    public function monthlyCashflow(int $months = 12): array
    {
        $start = now()->timezone('Africa/Lagos')->startOfMonth()->subMonths($months - 1);
        $income = DB::table('payment_allocations')->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.received_on', '>=', $start->toDateString())
            ->get(['payments.received_on', 'payment_allocations.amount_kobo'])
            ->groupBy(fn (object $r): string => Carbon::parse((string) $r->received_on)->format('Y-m'))
            ->map(fn ($g): int => (int) $g->sum('amount_kobo'));
        $unallocated = Payment::query()->where('received_on', '>=', $start->toDateString())->get(['received_on', 'unallocated_kobo'])
            ->groupBy(fn ($p) => $p->received_on->format('Y-m'))->map(fn ($g) => (int) $g->sum('unallocated_kobo'));
        $spend = Expense::query()->where('spent_on', '>=', $start->toDateString())->get(['spent_on', 'amount_kobo'])
            ->groupBy(fn (Expense $e) => $e->spent_on->format('Y-m'))->map(fn ($g) => (int) $g->sum('amount_kobo'));

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $out[] = ['month' => $key, 'label' => $m->format('M'), 'income' => (int) ($income[$key] ?? 0) + (int) ($unallocated[$key] ?? 0), 'expenses' => (int) ($spend[$key] ?? 0)];
        }

        return $out;
    }

    /** @return array<string, int> category label => kobo */
    public function expenseMix(Carbon $from, Carbon $to): array
    {
        $out = [];
        foreach (ExpenseCategory::cases() as $c) {
            $sum = (int) Expense::query()->where('category', $c->value)->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->sum('amount_kobo');
            if ($sum > 0) {
                $out[$c->label()] = $sum;
            }
        }
        arsort($out);

        return $out;
    }
}
