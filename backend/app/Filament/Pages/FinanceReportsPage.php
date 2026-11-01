<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Finance\FinanceReports;
use App\Domain\Finance\ReportExporter;
use App\Enums\Permission;
use App\Support\Money;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PRD 10.5 reports, each downloadable as CSV, PDF or Word.
 */
class FinanceReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Finance reports';

    protected static ?string $title = 'Finance reports';

    protected static ?string $slug = 'finance-reports';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.finance-reports';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ReportsView->value) ?? false;
    }

    public function mount(): void
    {
        $this->from = now()->startOfYear()->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(): array
    {
        return [Carbon::parse($this->from ?: now()->startOfYear()), Carbon::parse($this->to ?: now())->endOfDay()];
    }

    public function periodLabel(): string
    {
        [$f, $t] = $this->range();

        return $f->format('j M Y').' – '.$t->format('j M Y');
    }

    /** @return array<string, array{title: string, headings: list<string>, rows: list<list<string|int>>}> */
    public function getReports(): array
    {
        [$f, $t] = $this->range();
        $r = app(FinanceReports::class);
        $m = fn (int $k): string => Money::kobo($k)->format();

        $ageing = $r->ageing();
        $vat = $r->vatSummary($f, $t);

        return [
            'revenue_month' => ['title' => 'Revenue by month (excl. VAT)', 'headings' => ['Month', 'Invoices', 'Revenue'],
                'rows' => array_map(fn ($x) => [$x['label'], $x['count'], $m($x['revenue'])], collect($r->revenueBy('month', $f, $t))->sortBy('label')->values()->all())],
            'revenue_customer' => ['title' => 'Revenue by customer', 'headings' => ['Customer', 'Invoices', 'Revenue'],
                'rows' => array_map(fn ($x) => [$x['label'], $x['count'], $m($x['revenue'])], $r->revenueBy('customer', $f, $t))],
            'revenue_route' => ['title' => 'Revenue by route', 'headings' => ['Route', 'Invoices', 'Revenue'],
                'rows' => array_map(fn ($x) => [$x['label'], $x['count'], $m($x['revenue'])], $r->revenueBy('route', $f, $t))],
            'expenses_category' => ['title' => 'Expenses by category', 'headings' => ['Category', 'Amount'],
                'rows' => array_map(fn ($x) => [$x['label'], $m($x['amount'])], $r->expensesBy('category', $f, $t))],
            'profit_truck' => ['title' => 'Profit per truck', 'headings' => ['Truck', 'Revenue', 'Expenses', 'Profit'],
                'rows' => array_map(fn ($x) => [$x['label'], $m($x['revenue']), $m($x['expenses']), $m($x['profit'])], $r->profitBy('truck', $f, $t))],
            'profit_trip' => ['title' => 'Profit per trip', 'headings' => ['Trip', 'Revenue', 'Expenses', 'Profit'],
                'rows' => array_map(fn ($x) => [$x['label'], $m($x['revenue']), $m($x['expenses']), $m($x['profit'])], $r->profitBy('trip', $f, $t))],
            'ageing' => ['title' => 'Receivables ageing (today)', 'headings' => ['Customer', 'Invoice', 'Due', 'Days overdue', 'Balance'],
                'rows' => array_map(fn ($x) => [$x['customer'], $x['number'], $x['due'], $x['days'], $m($x['balance'])], $ageing['rows'])],
            'ageing_buckets' => ['title' => 'Receivables by age', 'headings' => ['Days overdue', 'Balance'],
                'rows' => array_map(fn ($k, $v) => [$k, $m($v)], array_keys($ageing['buckets']), $ageing['buckets'])],
            'tax' => ['title' => 'VAT and WHT summary', 'headings' => ['Item', 'Amount'],
                'rows' => [['Invoices issued', $vat['invoices']], ['Taxable value', $m($vat['taxable'])], ['Output VAT', $m($vat['output_vat'])], ['WHT deducted by customers', $m($r->whtReceivable($f, $t))]]],
        ];
    }

    public function download(string $key, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['csv', 'pdf', 'docx'], true), 404);
        $rep = $this->getReports()[$key] ?? abort(404);
        $bytes = app(ReportExporter::class)->export($rep['title'], $this->periodLabel(), $rep['headings'], $rep['rows'], $format);

        return response()->streamDownload(fn () => print ($bytes), 'gea-'.str_replace('_', '-', $key).'-'.now()->format('Ymd').'.'.$format);
    }
}
