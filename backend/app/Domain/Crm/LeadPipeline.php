<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Enums\LeadStage;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stage changes on the pipeline board (PRD 9.2). Every change is logged as an activity.
 */
final class LeadPipeline
{
    public function move(Lead $lead, LeadStage $to, ?User $by = null, ?string $lostReason = null): Lead
    {
        if ($to === LeadStage::Lost && blank($lostReason)) {
            throw new InvalidArgumentException('A reason is required to mark a lead as lost.');
        }

        if ($lead->stage === $to) {
            return $lead;
        }

        return DB::transaction(function () use ($lead, $to, $by, $lostReason): Lead {
            $from = $lead->stage;

            $lead->stage = $to;
            $lead->lost_reason = $to === LeadStage::Lost ? $lostReason : null;
            $lead->won_at = $to === LeadStage::Won ? now() : null;
            $lead->lost_at = $to === LeadStage::Lost ? now() : null;
            if ($to !== LeadStage::New && $lead->first_contacted_at === null) {
                $lead->first_contacted_at = now();
            }
            $lead->save();

            $lead->activities()->create([
                'contact_id' => $lead->contact_id,
                'type' => 'stage_change',
                'body' => "Moved from {$from->label()} to {$to->label()}".($lostReason ? ": {$lostReason}" : '.'),
                'user_id' => $by?->id,
                'at' => now(),
            ]);

            if (! $lead->isOpen()) {
                $lead->followUps()->where('status', 'open')->update(['status' => 'done']);
            }

            if ($to === LeadStage::Won) {
                $this->convertToCustomer($lead);
            }

            return $lead;
        });
    }

    /**
     * Won leads become customers (PRD 9.2). An existing customer for the same company or contact is reused.
     */
    public function convertToCustomer(Lead $lead): Customer
    {
        if ($lead->customer_id !== null && $lead->customer !== null) {
            return $lead->customer;
        }

        $lead->loadMissing('contact.company');
        $contact = $lead->contact;
        $company = $contact->company;

        $customer = Customer::query()
            ->when($company !== null, fn ($q) => $q->where('company_id', $company?->id), fn ($q) => $q->where('contact_id', $contact->id))
            ->first()
            ?? Customer::query()->create([
                'company_id' => $company?->id,
                'contact_id' => $contact->id,
                'name' => $company !== null ? $company->name : $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
            ]);

        $lead->customer()->associate($customer)->save();

        return $customer;
    }
}
