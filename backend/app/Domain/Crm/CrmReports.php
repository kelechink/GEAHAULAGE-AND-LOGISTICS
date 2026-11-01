<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Crm\Lead;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * CRM report figures (PRD 9.2): leads by source, conversion by stage, response time, won value by month.
 */
final class CrmReports
{
    /** @return list<array{source: string, leads: int, won: int}> */
    public function bySource(Carbon $from, Carbon $to): array
    {
        $rows = [];
        foreach (LeadSource::cases() as $source) {
            $q = Lead::query()->where('source', $source->value)->whereBetween('created_at', [$from, $to]);
            $leads = (clone $q)->count();
            if ($leads === 0) {
                continue;
            }
            $rows[] = ['source' => $source->label(), 'leads' => $leads, 'won' => (clone $q)->where('stage', LeadStage::Won->value)->count()];
        }

        return $rows;
    }

    /**
     * How many leads reached at least each stage, and the share of all leads that is.
     *
     * @return list<array{stage: string, reached: int, percent: int}>
     */
    public function funnel(Carbon $from, Carbon $to): array
    {
        $leads = Lead::query()->whereBetween('created_at', [$from, $to])->get(['stage']);
        $total = $leads->count();
        $order = [LeadStage::New, LeadStage::Contacted, LeadStage::Qualified, LeadStage::Quoted, LeadStage::Won];
        $rank = array_flip(array_map(fn (LeadStage $s) => $s->value, $order));

        $rows = [];
        foreach ($order as $i => $stage) {
            $reached = $leads->filter(fn (Lead $l): bool => $l->stage !== LeadStage::Lost && $rank[$l->stage->value] >= $i)->count();
            if ($stage === LeadStage::New) {
                $reached = $total;
            }
            $rows[] = ['stage' => $stage->label(), 'reached' => $reached, 'percent' => $total > 0 ? intdiv($reached * 100, $total) : 0];
        }
        $lost = $leads->where('stage', LeadStage::Lost)->count();
        $rows[] = ['stage' => 'Lost', 'reached' => $lost, 'percent' => $total > 0 ? intdiv($lost * 100, $total) : 0];

        return $rows;
    }

    /**
     * Median hours from enquiry to first contact, and the share contacted within one working day (PRD 2 target: 90%).
     *
     * @return array{median_hours: int|null, within_target_percent: int|null, contacted: int}
     */
    public function responseTime(Carbon $from, Carbon $to): array
    {
        $leads = Lead::query()->whereBetween('created_at', [$from, $to])->whereNotNull('first_contacted_at')->get(['created_at', 'first_contacted_at']);
        if ($leads->isEmpty()) {
            return ['median_hours' => null, 'within_target_percent' => null, 'contacted' => 0];
        }

        $hours = $leads->map(fn (Lead $l): int => (int) $l->created_at?->diffInHours($l->first_contacted_at))->sort()->values();
        $within = $leads->filter(fn (Lead $l): bool => $l->first_contacted_at !== null && $l->created_at !== null
            && $l->first_contacted_at->lte(WorkingDays::next($l->created_at)))->count();

        return [
            'median_hours' => (int) $hours[intdiv($hours->count(), 2)],
            'within_target_percent' => intdiv($within * 100, $leads->count()),
            'contacted' => $leads->count(),
        ];
    }

    /** @return list<array{month: string, won: int, value: string}> */
    public function wonByMonth(int $months = 12): array
    {
        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $q = Lead::query()->where('stage', LeadStage::Won->value)->whereBetween('won_at', [$start, $start->copy()->endOfMonth()]);
            $rows[] = [
                'month' => $start->format('M Y'),
                'won' => (clone $q)->count(),
                'value' => Money::kobo((int) (clone $q)->sum('estimated_value_kobo'))->format(),
            ];
        }

        return $rows;
    }
}
