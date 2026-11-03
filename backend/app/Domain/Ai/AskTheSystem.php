<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Finance\FinanceReports;
use App\Models\Crm\Lead;
use App\Models\Driver;
use App\Models\Finance\Invoice;
use App\Models\Shipment;
use App\Models\Truck;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * "Ask the system" for Management (PRD 12.5). The AI only picks one whitelisted report and its parameters
 * from the question; Laravel runs the report and shows the table. Results never go back to the AI, so no
 * financial data reaches a free-tier provider. Never free-form SQL.
 */
final class AskTheSystem
{
    public function __construct(private readonly AiProvider $ai, private readonly FinanceReports $finance) {}

    /** @return list<array{name: string, description: string, parameters: array<string, mixed>}> */
    public static function tools(): array
    {
        $month = ['type' => 'string', 'description' => 'Month as YYYY-MM'];

        return [
            ['name' => 'trucks_by_profit', 'description' => 'Trucks ranked by profit (revenue less expenses) for a month.', 'parameters' => ['type' => 'object', 'properties' => ['month' => $month]]],
            ['name' => 'customers_owing', 'description' => 'Customers with unpaid balances above a naira amount.', 'parameters' => ['type' => 'object', 'properties' => ['min_naira' => ['type' => 'number']]]],
            ['name' => 'revenue_by_customer', 'description' => 'Revenue per customer for a month.', 'parameters' => ['type' => 'object', 'properties' => ['month' => $month]]],
            ['name' => 'revenue_by_route', 'description' => 'Revenue per route for a month.', 'parameters' => ['type' => 'object', 'properties' => ['month' => $month]]],
            ['name' => 'delayed_trips', 'description' => 'Trips currently delayed, with reasons.', 'parameters' => ['type' => 'object', 'properties' => (object) []]],
            ['name' => 'available_trucks', 'description' => 'Trucks available now, optionally with a minimum capacity in tonnes.', 'parameters' => ['type' => 'object', 'properties' => ['min_tonnes' => ['type' => 'number']]]],
            ['name' => 'expiring_documents', 'description' => 'Truck and driver documents expiring within a number of days.', 'parameters' => ['type' => 'object', 'properties' => ['days' => ['type' => 'integer']]]],
            ['name' => 'leads_by_source', 'description' => 'Leads by source over the last number of days.', 'parameters' => ['type' => 'object', 'properties' => ['days' => ['type' => 'integer']]]],
        ];
    }

    /** @return array{title: string, columns: list<string>, rows: list<list<string>>, note: string|null} */
    public function ask(string $question): array
    {
        [$tool, $args, $note] = $this->route($question);

        return $this->run($tool, $args) + ['note' => $note];
    }

    /** @return array{0: string, 1: array<string, mixed>, 2: string|null} */
    private function route(string $question): array
    {
        try {
            $res = $this->ai->chat([
                ['role' => 'system', 'content' => 'Pick the one report tool that answers the manager\'s question and fill its parameters. Today is '.now('Africa/Lagos')->toDateString().'. If none fits, reply with one sentence listing what you can answer.'],
                ['role' => 'user', 'content' => $question],
            ], self::tools(), ['temperature' => 0]);
            if ($res->wantsTools()) {
                return [$res->toolCalls[0]->name, $res->toolCalls[0]->arguments, null];
            }

            return ['none', [], $res->text];
        } catch (AiUnavailable) {
            return [...$this->keywordRoute($question), 'The AI is offline, so this was matched by keywords.'];
        }
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    public function keywordRoute(string $q): array
    {
        $q = strtolower($q);
        $month = preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december)\b/', $q, $m)
            ? Carbon::parse('1 '.$m[1].' '.now()->year)->format('Y-m') : now()->format('Y-m');
        $num = preg_match('/(\d[\d,.]*)\s*(k|m|million)?/', $q, $n)
            ? (float) str_replace(',', '', $n[1]) * match ($n[2] ?? '') {
                'k' => 1000, 'm', 'million' => 1_000_000, default => 1
            } : 0;

        return match (true) {
            str_contains($q, 'truck') && (str_contains($q, 'money') || str_contains($q, 'profit') || str_contains($q, 'earn')) => ['trucks_by_profit', ['month' => $month]],
            str_contains($q, 'owe') || str_contains($q, 'owing') || str_contains($q, 'debt') => ['customers_owing', ['min_naira' => $num]],
            str_contains($q, 'route') => ['revenue_by_route', ['month' => $month]],
            str_contains($q, 'customer') => ['revenue_by_customer', ['month' => $month]],
            str_contains($q, 'delay') => ['delayed_trips', []],
            str_contains($q, 'available') || str_contains($q, 'free truck') => ['available_trucks', ['min_tonnes' => $num]],
            str_contains($q, 'expir') || str_contains($q, 'document') => ['expiring_documents', ['days' => (int) ($num ?: 30)]],
            str_contains($q, 'lead') => ['leads_by_source', ['days' => (int) ($num ?: 30)]],
            default => ['none', []],
        };
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array{title: string, columns: list<string>, rows: list<list<string>>}
     */
    public function run(string $tool, array $a): array
    {
        $month = self::month($a['month'] ?? null);
        $fmt = fn (int $k): string => Money::kobo($k)->format();

        return match ($tool) {
            'trucks_by_profit' => ['title' => 'Trucks by profit, '.$month->format('F Y'), 'columns' => ['Truck', 'Revenue', 'Expenses', 'Profit'],
                'rows' => array_map(fn (array $r): array => [$r['label'], $fmt($r['revenue']), $fmt($r['expenses']), $fmt($r['profit'])],
                    $this->finance->profitBy('truck', $month->copy()->startOfMonth(), $month->copy()->endOfMonth()))],
            'customers_owing' => $this->owing((float) ($a['min_naira'] ?? 0)),
            'revenue_by_customer', 'revenue_by_route' => ['title' => 'Revenue by '.($tool === 'revenue_by_route' ? 'route' : 'customer').', '.$month->format('F Y'), 'columns' => ['Name', 'Revenue'],
                'rows' => array_map(fn (array $r): array => [(string) $r['label'], $fmt((int) $r['revenue'])],
                    $this->finance->revenueBy($tool === 'revenue_by_route' ? 'route' : 'customer', $month->copy()->startOfMonth(), $month->copy()->endOfMonth()))],
            'delayed_trips' => ['title' => 'Delayed trips', 'columns' => ['Code', 'Route', 'Reason', 'Last update'],
                'rows' => Shipment::query()->where('stage', 'delayed')->get()->map(fn (Shipment $s): array => [$s->tracking_code, "{$s->origin} → {$s->destination}", (string) $s->delay_reason, $s->last_update_at?->diffForHumans() ?? '—'])->all()],
            'available_trucks' => ['title' => 'Available trucks', 'columns' => ['Truck', 'Type', 'Capacity (t)'],
                'rows' => Truck::query()->where('status', 'available')->where('capacity_tonnes', '>=', (float) ($a['min_tonnes'] ?? 0))->orderBy('capacity_tonnes')->get()
                    ->map(fn (Truck $t): array => [$t->registration, (string) (Truck::TYPES[$t->type] ?? $t->type), (string) $t->capacity_tonnes])->all()],
            'expiring_documents' => $this->expiring((int) ($a['days'] ?? 30)),
            'leads_by_source' => $this->leads((int) ($a['days'] ?? 30)),
            default => ['title' => 'I can answer questions about', 'columns' => ['Report'], 'rows' => array_map(fn (array $t): array => [$t['description']], self::tools())],
        };
    }

    /** @return array{title: string, columns: list<string>, rows: list<list<string>>} */
    private function owing(float $minNaira): array
    {
        $min = (int) round($minNaira * 100);
        $rows = Invoice::query()->receivable()->with('customer')->get()->groupBy('customer_id')
            ->map(fn ($g): array => ['name' => $g->first()->customer->name, 'kobo' => (int) $g->sum('balance_kobo'), 'count' => $g->count()])
            ->filter(fn (array $r): bool => $r['kobo'] > $min)->sortByDesc('kobo')
            ->map(fn (array $r): array => [$r['name'], Money::kobo($r['kobo'])->format(), (string) $r['count']])->values()->all();

        return ['title' => 'Customers owing more than '.Money::kobo($min)->format(), 'columns' => ['Customer', 'Owed', 'Invoices'], 'rows' => $rows];
    }

    /** @return array{title: string, columns: list<string>, rows: list<list<string>>} */
    private function expiring(int $days): array
    {
        $rows = [];
        foreach (Truck::query()->get() as $t) {
            foreach ($t->expiringDocuments($days) as $d) {
                $rows[] = ['Truck '.$t->registration, $d['document'], $d['expires_on']->format('j M Y')];
            }
        }
        foreach (Driver::query()->where('is_active', true)->whereDate('licence_expires_on', '<=', now()->addDays($days)->toDateString())->get() as $d) {
            $rows[] = ['Driver '.$d->name, 'Driving licence', $d->licence_expires_on?->format('j M Y') ?? ''];
        }

        return ['title' => "Documents expiring in {$days} days", 'columns' => ['Who', 'Document', 'Expires'], 'rows' => $rows];
    }

    /** @return array{title: string, columns: list<string>, rows: list<list<string>>} */
    private function leads(int $days): array
    {
        $rows = Lead::query()->where('created_at', '>=', now()->subDays($days))->get()->groupBy(fn (Lead $l): string => $l->source->label())
            ->map(fn ($g, string $k): array => [$k, (string) $g->count()])->sortByDesc(fn (array $r): int => (int) $r[1])->values()->all();

        return ['title' => "Leads by source, last {$days} days", 'columns' => ['Source', 'Leads'], 'rows' => $rows];
    }

    private static function month(mixed $value): Carbon
    {
        try {
            return is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value) === 1
                ? Carbon::createFromFormat('Y-m-d', $value.'-01', 'Africa/Lagos') ?? now('Africa/Lagos')
                : now('Africa/Lagos');
        } catch (\Throwable) {
            return now('Africa/Lagos');
        }
    }
}
