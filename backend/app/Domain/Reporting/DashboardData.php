<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Finance\FinanceReports;
use App\Enums\LeadSource;
use App\Enums\Permission;
use App\Enums\ShipmentStage;
use App\Enums\TruckStatus;
use App\Models\Ai\Conversation;
use App\Models\Ai\Digest;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\Driver;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\Truck;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Figures for the admin dashboard (PRD 10.6 and the client's request for colourful income,
 * expenditure and request charts with a short summary). Each block is only built for users
 * who may see it.
 */
final class DashboardData
{
    public function __construct(private readonly FinanceReports $finance) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $canFinance = $user->can(Permission::ReportsView->value);
        $canCrm = $user->can(Permission::CrmView->value);
        $canOps = $user->can(Permission::ShipmentsView->value);

        return [
            'kpis' => $this->kpis($canFinance, $canCrm, $canOps),
            'summary' => $this->summary($canFinance, $canCrm, $canOps),
            'briefing' => $canOps || $user->can(Permission::AiManage->value) ? $this->briefing() : null,
            'alerts' => $this->alerts($canFinance, $canCrm, $canOps, $user),
            'charts' => array_filter([
                'cashflow' => $canFinance ? $this->cashflow() : null,
                'requests' => $canCrm ? $this->requestsByWeek() : null,
                'stages' => $canOps ? $this->shipmentsByStage() : null,
                'expenses' => $canFinance ? $this->expenseMix() : null,
            ]),
        ];
    }

    /** Today's 7am digest (PRD 12.5), if it has been built. */
    private function briefing(): ?string
    {
        return Digest::query()->whereDate('day', now('Africa/Lagos')->toDateString())->value('summary');
    }

    private function monthStart(int $monthsAgo = 0): Carbon
    {
        return now()->timezone('Africa/Lagos')->startOfMonth()->subMonths($monthsAgo);
    }

    /** "₦52.99m" for tiles; the exact figure goes in the tooltip. */
    private function compact(int $kobo): string
    {
        $n = intdiv(abs($kobo), 100);
        $sign = $kobo < 0 ? '-' : '';

        return match (true) {
            $n >= 1_000_000_000 => $sign.'₦'.number_format($n / 1_000_000_000, 2).'bn',
            $n >= 1_000_000 => $sign.'₦'.number_format($n / 1_000_000, 2).'m',
            $n >= 10_000 => $sign.'₦'.number_format($n / 1_000, 1).'k',
            default => Money::kobo($kobo)->format(),
        };
    }

    private function incomeBetween(Carbon $from, Carbon $to): int
    {
        return (int) Payment::query()->whereDate('received_on', '>=', $from->toDateString())->whereDate('received_on', '<=', $to->toDateString())->sum('amount_kobo');
    }

    private function expensesBetween(Carbon $from, Carbon $to): int
    {
        return (int) Expense::query()->whereDate('spent_on', '>=', $from->toDateString())->whereDate('spent_on', '<=', $to->toDateString())->sum('amount_kobo');
    }

    /**
     * "+12% vs last month" with a direction for the arrow.
     *
     * @return array{text: string, dir: string}
     */
    private function delta(int $now, int $before): array
    {
        if ($before === 0) {
            return ['text' => $now > 0 ? 'New this month' : 'No change', 'dir' => $now > 0 ? 'up' : 'flat'];
        }
        $pct = intdiv(($now - $before) * 100, abs($before));

        return ['text' => ($pct > 0 ? '+' : '').$pct.'% vs last month', 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat')];
    }

    /** @return list<array<string, mixed>> */
    private function kpis(bool $fin, bool $crm, bool $ops): array
    {
        $tiles = [];
        $thisM = $this->monthStart();
        $lastM = $this->monthStart(1);
        $lastMEndSameDay = $lastM->copy()->addDays(min(now()->day, $lastM->daysInMonth) - 1)->endOfDay();

        if ($fin) {
            $income = $this->incomeBetween($thisM, now());
            $incomePrev = $this->incomeBetween($lastM, $lastMEndSameDay);
            $spend = $this->expensesBetween($thisM, now());
            $spendPrev = $this->expensesBetween($lastM, $lastMEndSameDay);
            $receivable = (int) Invoice::query()->receivable()->sum('balance_kobo');
            $overdue = Invoice::query()->where('status', 'overdue');

            $tiles[] = ['key' => 'income', 'slot' => 1, 'icon' => 'heroicon-o-arrow-trending-up', 'label' => 'Income this month', 'value' => $this->compact($income), 'full' => Money::kobo($income)->format(),
                'note' => $this->delta($income, $incomePrev)['text'].' (same days)', 'dir' => $this->delta($income, $incomePrev)['dir'], 'good' => 'up'];
            $tiles[] = ['key' => 'expenses', 'slot' => 2, 'icon' => 'heroicon-o-arrow-trending-down', 'label' => 'Expenses this month', 'value' => $this->compact($spend), 'full' => Money::kobo($spend)->format(),
                'note' => $this->delta($spend, $spendPrev)['text'].' (same days)', 'dir' => $this->delta($spend, $spendPrev)['dir'], 'good' => 'down'];
            $tiles[] = ['key' => 'receivable', 'slot' => 4, 'icon' => 'heroicon-o-banknotes', 'label' => 'Owed to GEA', 'value' => $this->compact($receivable), 'full' => Money::kobo($receivable)->format(),
                'note' => (clone $overdue)->count().' overdue · '.Money::kobo((int) (clone $overdue)->sum('balance_kobo'))->format(), 'dir' => 'flat', 'good' => null];
        }
        if ($ops) {
            $active = Shipment::query()->active()->count();
            $moving = Shipment::query()->whereIn('stage', ['in_transit', 'delayed'])->count();
            $delayed = Shipment::query()->where('stage', 'delayed')->count();
            $available = Truck::query()->where('status', TruckStatus::Available->value)->count();
            $workshop = Truck::query()->where('status', TruckStatus::Maintenance->value)->count();
            $tiles[] = ['key' => 'trips', 'slot' => 7, 'icon' => 'heroicon-o-truck', 'label' => 'Active trips', 'value' => (string) $active,
                'note' => "{$moving} on the road · {$delayed} delayed · {$available} trucks free · {$workshop} in maintenance", 'dir' => 'flat', 'good' => null];

            // Deliveries this month and on-time share: a delivery is on time if the trip was never marked DELAYED (PRD 10.6).
            $delivered = Shipment::query()->whereNotNull('delivered_at')->where('delivered_at', '>=', $thisM)->pluck('id');
            $late = $delivered->isEmpty() ? 0 : ShipmentEvent::query()->whereIn('shipment_id', $delivered)->where('to_stage', 'delayed')->distinct()->count('shipment_id');
            $onTime = $delivered->isEmpty() ? null : intdiv(($delivered->count() - $late) * 100, $delivered->count());
            $tiles[] = ['key' => 'deliveries', 'slot' => 6, 'icon' => 'heroicon-o-check-badge', 'label' => 'Delivered this month', 'value' => (string) $delivered->count(),
                'note' => $onTime === null ? 'No deliveries yet this month' : "{$onTime}% on time", 'dir' => 'flat', 'good' => null];
        }
        if ($crm) {
            $week = Lead::query()->where('created_at', '>=', now()->subDays(7))->count();
            $prevWeek = Lead::query()->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
            $due = FollowUp::query()->where('status', 'open')->where('due_at', '<=', now()->endOfDay())->count();
            $tiles[] = ['key' => 'requests', 'slot' => 5, 'icon' => 'heroicon-o-inbox-arrow-down', 'label' => 'Quote requests this week', 'value' => (string) $week,
                'note' => ($week - $prevWeek >= 0 ? '+' : '').($week - $prevWeek).' vs last week · '.$due.' follow-ups due', 'dir' => $week > $prevWeek ? 'up' : ($week < $prevWeek ? 'down' : 'flat'), 'good' => 'up'];
        }
        if ($fin) {
            $net = $this->incomeBetween($thisM, now()) - $this->expensesBetween($thisM, now());
            $tiles[] = ['key' => 'net', 'slot' => 3, 'icon' => 'heroicon-o-scale', 'label' => 'Net cash this month', 'value' => $this->compact($net), 'full' => Money::kobo($net)->format(),
                'note' => match (true) {
                    $net > 0 => 'Income ahead of spending', $net < 0 => 'Spending ahead of income', default => 'No money in or out yet this month'
                }, 'dir' => $net > 0 ? 'up' : ($net < 0 ? 'down' : 'flat'), 'good' => 'up'];
        }

        return $tiles;
    }

    /** @return array<string, mixed> */
    private function cashflow(): array
    {
        $rows = $this->finance->monthlyCashflow(12);

        return [
            'labels' => array_map(fn ($r) => Carbon::createFromFormat('Y-m', $r['month'])?->format('M y') ?? $r['label'], $rows),
            'income' => array_map(fn ($r) => intdiv($r['income'], 100), $rows),
            'expenses' => array_map(fn ($r) => intdiv($r['expenses'], 100), $rows),
            'net' => array_map(fn ($r) => intdiv($r['income'] - $r['expenses'], 100), $rows),
            'totals' => [
                'income' => Money::kobo(array_sum(array_column($rows, 'income')))->format(),
                'expenses' => Money::kobo(array_sum(array_column($rows, 'expenses')))->format(),
            ],
        ];
    }

    /**
     * Quote requests per week for 12 weeks, by where they came from. Four groups at most (palette rule):
     * website, phone and walk-in, WhatsApp and referrals, AI chat.
     *
     * @return array<string, mixed>
     */
    private function requestsByWeek(): array
    {
        $groups = [
            'Website' => [LeadSource::WebsiteForm],
            'Phone and walk-in' => [LeadSource::Phone, LeadSource::WalkIn],
            'WhatsApp and referral' => [LeadSource::WhatsappClick, LeadSource::Referral],
            'AI chat' => [LeadSource::AiChat],
        ];
        $start = now()->timezone('Africa/Lagos')->startOfWeek()->subWeeks(11);
        $leads = Lead::query()->where('created_at', '>=', $start)->where('source', '!=', LeadSource::Careers->value)->get(['source', 'created_at']);

        $labels = [];
        $series = array_fill_keys(array_keys($groups), array_fill(0, 12, 0));
        for ($w = 0; $w < 12; $w++) {
            $labels[] = $start->copy()->addWeeks($w)->format('j M');
        }
        foreach ($leads as $l) {
            $w = (int) floor($start->diffInDays($l->created_at->timezone('Africa/Lagos')) / 7);
            if ($w < 0 || $w > 11) {
                continue;
            }
            foreach ($groups as $name => $sources) {
                if (in_array($l->source, $sources, true)) {
                    $series[$name][$w]++;
                }
            }
        }

        return ['labels' => $labels, 'series' => array_filter($series, fn ($v) => array_sum($v) > 0) ?: ['Website' => array_fill(0, 12, 0)], 'total' => $leads->count()];
    }

    /** @return array<string, mixed> */
    private function shipmentsByStage(): array
    {
        $counts = Shipment::query()->active()->selectRaw('stage, count(*) as c')->groupBy('stage')->pluck('c', 'stage');
        $labels = [];
        $values = [];
        foreach ([ShipmentStage::Booked, ShipmentStage::Assigned, ShipmentStage::Loading, ShipmentStage::Loaded, ShipmentStage::InTransit, ShipmentStage::Delayed, ShipmentStage::Arrived] as $s) {
            $labels[] = $s->label();
            $values[] = (int) ($counts[$s->value] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array<string, mixed> */
    private function expenseMix(): array
    {
        $from = now()->timezone('Africa/Lagos')->startOfYear();
        $mix = $this->finance->expenseMix($from, now());

        return ['labels' => array_keys($mix), 'values' => array_map(fn (int $k) => intdiv($k, 100), array_values($mix)), 'since' => $from->format('j M Y')];
    }

    /**
     * A few plain sentences about what is happening right now.
     *
     * @return list<string>
     */
    private function summary(bool $fin, bool $crm, bool $ops): array
    {
        $lines = [];
        if ($ops) {
            $moving = Shipment::query()->whereIn('stage', ['in_transit', 'delayed'])->count();
            $delivered = Shipment::query()->where('delivered_at', '>=', now()->subDays(7))->count();
            $lines[] = "{$moving} trucks are on the road right now, and {$delivered} deliveries were completed in the last 7 days.";
        }
        if ($fin) {
            $m = $this->monthStart();
            $in = $this->incomeBetween($m, now());
            $out = $this->expensesBetween($m, now());
            $lines[] = 'This month GEA has received '.Money::kobo($in)->format().' and spent '.Money::kobo($out)->format().', a net of '.Money::kobo($in - $out)->format().'.';
            $topDebtor = Invoice::query()->receivable()->with('customer')->get()->groupBy('customer_id')
                ->map(fn ($g) => ['name' => $g->first()->customer->name, 'owed' => (int) $g->sum('balance_kobo')])->sortByDesc('owed')->first();
            if ($topDebtor !== null) {
                $lines[] = "The largest balance owed is {$topDebtor['name']} at ".Money::kobo($topDebtor['owed'])->format().'.';
            }
        }
        if ($crm) {
            $week = Lead::query()->where('created_at', '>=', now()->subDays(7))->count();
            $won = Lead::query()->where('won_at', '>=', now()->subDays(30))->count();
            $lines[] = "{$week} new quote requests came in this week; {$won} deals were won in the last 30 days.";
        }

        return $lines;
    }

    /**
     * Things that need someone's attention today (PRD 10.6 alerts panel).
     *
     * @return list<array{level: string, text: string, url: string|null}>
     */
    private function alerts(bool $fin, bool $crm, bool $ops, User $user): array
    {
        $a = [];
        if ($ops) {
            $stale = Shipment::query()->whereIn('stage', ['in_transit', 'delayed'])
                ->where(fn ($q) => $q->whereNull('last_update_at')->orWhere('last_update_at', '<', now()->subHour()))->count();
            if ($stale > 0) {
                $a[] = ['level' => 'serious', 'text' => "{$stale} trips have had no update for over an hour.", 'url' => url('/admin/update-console')];
            }
            $delayed = Shipment::query()->where('stage', 'delayed')->count();
            if ($delayed > 0) {
                $a[] = ['level' => 'warning', 'text' => "{$delayed} shipments are delayed.", 'url' => url('/admin/shipments')];
            }
            $soon = now()->addDays(14)->toDateString();
            $docs = Truck::query()->where(fn ($q) => $q->where('insurance_expires_on', '<=', $soon)->orWhere('roadworthiness_expires_on', '<=', $soon)->orWhere('licence_expires_on', '<=', $soon))->count()
                + Driver::query()->where('is_active', true)->where('licence_expires_on', '<=', $soon)->count();
            if ($docs > 0) {
                $a[] = ['level' => 'warning', 'text' => "{$docs} truck or driver documents expire within 14 days.", 'url' => url('/admin/trucks')];
            }
        }
        if ($fin) {
            $overdue = Invoice::query()->where('status', 'overdue');
            $n = (clone $overdue)->count();
            if ($n > 0) {
                $a[] = ['level' => 'critical', 'text' => "{$n} invoices are overdue, worth ".Money::kobo((int) $overdue->sum('balance_kobo'))->format().'.', 'url' => url('/admin/invoices')];
            }
            $drafts = Invoice::query()->where('status', 'draft')->whereNotNull('shipment_id')->count();
            if ($drafts > 0) {
                $a[] = ['level' => 'warning', 'text' => "{$drafts} delivered trips are waiting to be invoiced.", 'url' => url('/admin/invoices')];
            }
        }
        if ($user->can(Permission::AiInbox->value)) {
            $waiting = Conversation::query()->where('status', 'needs_human')->count();
            if ($waiting > 0) {
                $a[] = ['level' => 'serious', 'text' => "{$waiting} website chats are waiting for a person.", 'url' => url('/admin/ai-conversations')];
            }
        }
        if ($crm) {
            $missed = FollowUp::query()->where('status', 'open')->where('due_at', '<', now())->count();
            if ($missed > 0) {
                $a[] = ['level' => 'serious', 'text' => "{$missed} follow-ups are overdue.", 'url' => url('/admin/follow-ups')];
            }
        }

        return $a;
    }
}
