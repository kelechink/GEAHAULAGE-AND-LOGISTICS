<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Enums\LeadSource;
use App\Mail\NewLeadMail;
use App\Mail\QuoteReceivedMail;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use App\Settings\WebsiteSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Turns an enquiry into a CRM contact, lead, activity and follow-up (PRD 9.2),
 * then notifies sales and acknowledges the customer by email.
 */
final class LeadCaptureService
{
    public function __construct(
        private readonly WebsiteSettings $website,
        private readonly FollowUpAssigner $assigner,
    ) {}

    /**
     * @param  array{name: string, phone: string, email?: string|null, company?: string|null, service?: string|null, from?: string|null, to?: string|null, details?: string|null, consent_marketing?: bool}  $data
     */
    public function captureQuoteRequest(array $data, LeadSource $source = LeadSource::WebsiteForm): Lead
    {
        $lead = DB::transaction(function () use ($data, $source): Lead {
            $company = filled($data['company'] ?? null)
                ? Company::query()->firstOrCreate(['name' => trim((string) $data['company'])])
                : null;

            $contact = $this->findOrCreateContact($data, $source, $company);
            if (($data['consent_marketing'] ?? false) === true) {
                $contact->forceFill(['consent_marketing' => true])->save(); // opt-in only; never switched off here
            }

            $owner = $this->assigner->next();

            $lead = Lead::query()->create([
                'owner_user_id' => $owner?->id,
                'contact_id' => $contact->id,
                'company_id' => $company?->id,
                'source' => $source,
                'service_interest' => $data['service'] ?? null,
                'origin' => $data['from'] ?? null,
                'destination' => $data['to'] ?? null,
                'cargo' => $data['details'] ?? null,
                'next_follow_up_at' => WorkingDays::next(Carbon::now()),
            ]);

            $lead->activities()->create([
                'contact_id' => $contact->id,
                'type' => 'note',
                'body' => $source->label().' enquiry: '.$this->summary($data),
                'at' => now(),
            ]);

            $lead->followUps()->create([
                'due_at' => $lead->next_follow_up_at,
                'assigned_to' => $owner?->id,
                'note' => 'Contact the customer about their quote request.',
            ]);

            return $lead;
        });

        Mail::to($this->website->email_sales)->queue(new NewLeadMail($lead->load('contact')));

        $email = $data['email'] ?? null;
        if (filled($email) && $source === LeadSource::WebsiteForm) {
            Mail::to((string) $email)->queue(new QuoteReceivedMail((string) $data['name']));
        }

        return $lead;
    }

    /**
     * Staff quick-add for phone and walk-in enquiries (PRD 9.2): three fields minimum.
     */
    public function quickAdd(string $name, string $phone, LeadSource $source, ?string $note = null): Lead
    {
        return $this->captureQuoteRequest(['name' => $name, 'phone' => $phone, 'details' => $note], $source);
    }

    /**
     * @param  array{name: string, phone: string, email?: string|null}  $data
     */
    private function findOrCreateContact(array $data, LeadSource $source, ?Company $company): Contact
    {
        $phone = PhoneNumber::normalise($data['phone']);
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null;

        $contact = Contact::query()
            ->where(function ($q) use ($phone, $email): void {
                $q->where('phone', $phone);
                if ($email !== null) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();

        if ($contact === null) {
            return Contact::query()->create([
                'company_id' => $company?->id,
                'name' => trim($data['name']),
                'phone' => $phone,
                'email' => $email,
                'source' => $source->value,
            ]);
        }

        $contact->fill(array_filter([
            'email' => $contact->email ?? $email,
            'phone' => $contact->phone ?? $phone,
            'company_id' => $contact->company_id ?? $company?->id,
        ], static fn ($v): bool => $v !== null))->save();

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function summary(array $data): string
    {
        $parts = array_filter([
            $data['service'] ?? null,
            filled($data['from'] ?? null) || filled($data['to'] ?? null) ? ($data['from'] ?? '?').' to '.($data['to'] ?? '?') : null,
            $data['details'] ?? null,
        ]);

        return implode('. ', array_map('strval', $parts));
    }
}
