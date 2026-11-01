<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Models\Ai\Digest;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\Driver;
use App\Models\Finance\Invoice;
use App\Models\Shipment;
use App\Models\Truck;
use App\Settings\OperationsSettings;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * 7:00am digest for Management and Operations (PRD 12.5). Facts come from database queries; the AI only
 * writes a short summary from counts and names of towns. Money totals are never sent to the AI: Laravel
 * adds them to the email itself.
 */
final class DailyDigest
{
    public function __construct(private readonly AiProvider $ai, private readonly OperationsSettings $ops) {}

    /** @return array<string, mixed> */
    public function facts(?Carbon $day = null): array
    {
        $day ??= now('Africa/Lagos')->startOfDay();
        $cutoff = now()->subMinutes($this->ops->stale_after_minutes);

        $active = Shipment::query()->whereIn('stage', ['assigned', 'loading', 'loaded', 'in_transit', 'delayed', 'arrived'])->count();
        $stale = Shipment::query()->whereIn('stage', ['in_transit', 'delayed'])
            ->where(fn ($q) => $q->whereNull('last_update_at')->orWhere('last_update_at', '<', $cutoff))->count();
        $delayed = Shipment::query()->where('stage', 'delayed')->get(['tracking_code', 'origin', 'destination', 'delay_reason'])
            ->map(fn (Shipment $s): string => "{$s->tracking_code} {$s->origin}→{$s->destination}: ".($s->delay_reason ?: 'no reason'))->all();
        $pickups = Shipment::query()->whereDate('pickup_date', $day->toDateString())->whereNotIn('stage', ['cancelled'])->count();
        $arriving = Shipment::query()->whereIn('stage', ['in_transit', 'arrived'])->count();

        $overdue = Invoice::query()->where('status', 'overdue');
        $receivable = (int) Invoice::query()->receivable()->sum('balance_kobo');

        $soon = now()->addDays(14);
        $truckDocs = Truck::query()->get()->sum(fn (Truck $t): int => count($t->expiringDocuments(14)));
        $driverDocs = Driver::query()->where('is_active', true)->whereNotNull('licence_expires_on')->whereDate('licence_expires_on', '<=', $soon->toDateString())->count();

        $since = $day->copy()->subDay();

        return [
            'day' => $day->toDateString(),
            'active_trips' => $active,
            'stale_trips' => $stale,
            'delayed_trips' => $delayed,
            'pickups_today' => $pickups,
            'deliveries_expected' => $arriving,
            'overdue_invoices' => (clone $overdue)->count(),
            'overdue_kobo' => (int) (clone $overdue)->sum('balance_kobo'),
            'receivable_kobo' => $receivable,
            'expiring_documents' => $truckDocs + $driverDocs,
            'new_leads' => Lead::query()->where('created_at', '>=', $since)->count(),
            'hot_leads' => Lead::query()->whereIn('stage', ['new', 'contacted', 'quoted', 'negotiating'])->where('intent_score', '>=', 70)->count(),
            'missed_follow_ups' => FollowUp::query()->where('status', 'missed')->count(),
        ];
    }

    public function build(?Carbon $day = null): Digest
    {
        $facts = $this->facts($day);
        $provider = null;
        try {
            $res = $this->ai->chat([
                ['role' => 'system', 'content' => 'You write a 3 to 5 sentence morning briefing for the managing director of a Nigerian haulage company. Use only the facts given. Plain English, no headings, no lists, no invented numbers. Start with what needs action today.'],
                ['role' => 'user', 'content' => json_encode(self::forAi($facts), JSON_PRETTY_PRINT) ?: '{}'],
            ], [], ['max_tokens' => 300]);
            $summary = $res->text;
            $provider = $res->provider;
        } catch (AiUnavailable) {
            $summary = self::plain($facts);
        }

        $digest = Digest::query()->whereDate('day', $facts['day'])->first() ?? new Digest(['day' => $facts['day']]);
        $digest->fill(['summary' => $summary, 'facts' => $facts, 'provider' => $provider])->save();

        return $digest;
    }

    /**
     * Strips money from the facts before they reach the AI (PRD 12.1, 12.5).
     *
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    public static function forAi(array $facts): array
    {
        return array_diff_key($facts, array_flip(['overdue_kobo', 'receivable_kobo']));
    }

    /** @param  array<string, mixed>  $f */
    public static function plain(array $f): string
    {
        $s = [];
        $s[] = "{$f['active_trips']} trips are active, {$f['pickups_today']} pickups are planned today and {$f['deliveries_expected']} deliveries are expected.";
        if ($f['stale_trips'] > 0 || $f['delayed_trips'] !== []) {
            $s[] = "{$f['stale_trips']} trips have gone quiet and ".count($f['delayed_trips']).' are delayed.';
        }
        $s[] = "{$f['overdue_invoices']} invoices are overdue.";
        $s[] = "{$f['new_leads']} new leads came in since yesterday, {$f['hot_leads']} leads are hot and {$f['missed_follow_ups']} follow-ups were missed.";
        if ($f['expiring_documents'] > 0) {
            $s[] = "{$f['expiring_documents']} truck or driver documents expire in the next 14 days.";
        }

        return implode(' ', $s);
    }

    /** @param  array<string, mixed>  $f */
    public static function money(array $f): string
    {
        return 'Receivables: '.Money::kobo((int) $f['receivable_kobo'])->format().'; overdue: '.Money::kobo((int) $f['overdue_kobo'])->format().'.';
    }
}
