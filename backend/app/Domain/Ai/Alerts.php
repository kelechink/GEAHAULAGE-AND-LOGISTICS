<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Crm\WorkingDays;
use App\Enums\Role;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\ShipmentResource;
use App\Models\Crm\Lead;
use App\Models\Finance\Invoice;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\OpsAlert;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Real-time, rule-based alerts (PRD 12.5): delayed shipments, newly overdue invoices and leads not contacted
 * within one working day. Stale trips are handled by tracking:flag-stale. Each alert fires once per item.
 */
final class Alerts
{
    public function shipmentDelayed(Shipment $s): void
    {
        if (! Cache::add("alert:delayed:{$s->id}:".now()->toDateString(), true, now()->addDay())) {
            return;
        }
        Notification::send($this->users([Role::Operations, Role::Management]), new OpsAlert(
            "{$s->tracking_code} is delayed",
            "{$s->origin} → {$s->destination}: ".($s->delay_reason ?: 'no reason given').'.',
            ShipmentResource::getUrl('view', ['record' => $s]),
        ));
    }

    /** @return array{overdue: int, uncontacted: int} */
    public function sweep(): array
    {
        $overdue = 0;
        foreach (Invoice::query()->where('status', 'overdue')->with('customer')->get() as $i) {
            if (! Cache::add("alert:overdue:{$i->id}", true, now()->addDays(60))) {
                continue;
            }
            $overdue++;
            Notification::send($this->users([Role::Finance, Role::Management]), new OpsAlert(
                "Invoice {$i->displayNumber()} is overdue",
                $i->customer->name.' owes '.Money::kobo($i->balance_kobo)->format().', due '.$i->due_date?->format('j M').'.',
                InvoiceResource::getUrl('view', ['record' => $i]),
            ));
        }

        $uncontacted = 0;
        $cutoff = now();
        $leads = Lead::query()->where('stage', 'new')->whereNull('first_contacted_at')->with(['contact', 'owner'])->get()
            ->filter(fn (Lead $l): bool => $l->created_at !== null && WorkingDays::next($l->created_at)->lte($cutoff));
        foreach ($leads as $l) {
            if (! Cache::add("alert:uncontacted:{$l->id}", true, now()->addDays(30))) {
                continue;
            }
            $uncontacted++;
            $to = $this->users([Role::Management]);
            if ($l->owner !== null) {
                $to->push($l->owner);
            }
            Notification::send($to->unique('id'), new OpsAlert(
                'Lead not contacted in 1 working day',
                ($l->contact->name ?? 'A lead').' ('.$l->source->label().') is still waiting for a first call.',
                LeadResource::getUrl('edit', ['record' => $l]),
            ));
        }

        return ['overdue' => $overdue, 'uncontacted' => $uncontacted];
    }

    /**
     * @param  list<Role>  $roles
     * @return Collection<int, User>
     */
    private function users(array $roles): Collection
    {
        return User::query()->role(array_map(fn (Role $r): string => $r->value, $roles))->where('is_active', true)->get();
    }
}
