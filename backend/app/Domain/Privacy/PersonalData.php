<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

use App\Domain\Crm\PhoneNumber;
use App\Models\Ai\Conversation;
use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\JobApplication;
use App\Models\Privacy\PrivacyRequest;
use App\Models\TrackingSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

/**
 * Right of access and right to erasure under the Nigeria Data Protection Act 2023 (PRD 16): find everything
 * held about one person by email or phone, export it, or erase it.
 *
 * Erasing keeps what tax and accounting law requires (privacy policy, "How long we keep it"): a customer
 * with invoices keeps its name and invoices, but its phone, email and notes are cleared.
 */
final class PersonalData
{
    /** @return array{email: string|null, phone: string|null} */
    public static function identify(string $input): array
    {
        $input = trim($input);
        if (filter_var($input, FILTER_VALIDATE_EMAIL) !== false) {
            return ['email' => Str::lower($input), 'phone' => null];
        }
        $phone = PhoneNumber::normalise($input);
        if ($phone === null || strlen($phone) !== 13) {
            throw new InvalidArgumentException('Enter the person\'s email address or Nigerian phone number.');
        }

        return ['email' => null, 'phone' => $phone];
    }

    /**
     * @return array{contacts: Collection<int, Contact>, customers: Collection<int, Customer>, leads: Collection<int, Lead>,
     *     applications: Collection<int, JobApplication>, chats: Collection<int, Conversation>, subscriptions: Collection<int, TrackingSubscription>}
     */
    public function find(string $input): array
    {
        ['email' => $email, 'phone' => $phone] = self::identify($input);
        $match = fn (string $emailCol, string $phoneCol) => fn ($q) => $email !== null ? $q->where($emailCol, $email) : $q->where($phoneCol, $phone);

        $contacts = Contact::query()->where($match('email', 'phone'))->get();
        $customers = Customer::query()->where($match('email', 'phone'))->orWhereIn('contact_id', $contacts->pluck('id'))->get();

        return [
            'contacts' => $contacts,
            'customers' => $customers,
            'leads' => Lead::query()->whereIn('contact_id', $contacts->pluck('id'))->with('activities')->get(),
            'applications' => $phone !== null ? JobApplication::query()->where('phone', $phone)->get() : new Collection,
            'chats' => Conversation::query()->where($match('visitor_email', 'visitor_phone'))->with('messages')->get(),
            'subscriptions' => $email !== null ? TrackingSubscription::query()->where('email', $email)->with('shipment')->get() : new Collection,
        ];
    }

    /** @return array<string, int> */
    public function counts(string $input): array
    {
        return array_map(fn (Collection $c): int => $c->count(), $this->find($input));
    }

    /** Builds a zip (data.json) of everything held, and returns its path on the local disk. */
    public function export(string $input, User $by): string
    {
        $found = $this->find($input);
        $data = [
            'generated_at' => now()->toIso8601String(),
            'controller' => config('app.name'),
            'contacts' => $found['contacts']->map(fn (Contact $c): array => $c->only(['name', 'phone', 'email', 'whatsapp', 'role', 'source', 'consent_marketing', 'created_at']))->all(),
            'customer_accounts' => $found['customers']->map(fn (Customer $c): array => $c->only(['name', 'phone', 'email', 'billing_address', 'tin', 'created_at'])
                + ['shipments' => $c->shipments()->get(['tracking_code', 'origin', 'destination', 'cargo_description', 'stage', 'pickup_date'])->toArray(),
                    'invoices' => $c->invoices()->get(['number', 'issue_date', 'due_date', 'total_kobo', 'balance_kobo', 'status'])->toArray()])->all(),
            'enquiries' => $found['leads']->map(fn (Lead $l): array => $l->only(['service_interest', 'origin', 'destination', 'cargo', 'tonnage', 'preferred_date', 'created_at'])
                + ['source' => $l->source->label(), 'stage' => $l->stage->value, 'notes' => $l->activities->pluck('body')->all()])->all(),
            'job_applications' => $found['applications']->map(fn (JobApplication $a): array => $a->only(['name', 'phone', 'role', 'years_experience', 'licence_class', 'about', 'status', 'created_at']))->all(),
            'website_chats' => $found['chats']->map(fn (Conversation $c): array => ['started' => $c->created_at?->toIso8601String(),
                'messages' => $c->messages->map(fn ($m): array => ['from' => $m->role, 'text' => $m->body, 'at' => $m->created_at->toIso8601String()])->all()])->all(),
            'tracking_email_updates' => $found['subscriptions']->map(fn (TrackingSubscription $s): array => ['shipment' => $s->shipment?->tracking_code, 'since' => $s->created_at?->toIso8601String()])->all(),
        ];

        $path = 'privacy-exports/'.now()->format('Ymd-His').'-'.Str::random(8).'.zip';
        Storage::disk('local')->makeDirectory('privacy-exports');
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($path), ZipArchive::CREATE);
        $zip->addFromString('data.json', (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('README.txt', 'Personal data held by '.config('app.name').' about the person who asked, as of '.now('Africa/Lagos')->toDayDateTimeString().".\nAmounts are in kobo (100 kobo = 1 naira).\n");
        $zip->close();

        $this->record('export', $input, $by, $this->countsOf($found));

        return $path;
    }

    /** @return array<string, int|string> what was erased, and what was kept for legal reasons */
    public function erase(string $input, User $by): array
    {
        $found = $this->find($input);
        $kept = 0;

        DB::transaction(function () use ($found, &$kept): void {
            foreach ($found['contacts'] as $c) {
                $c->forceFill(['name' => 'Erased contact #'.$c->id, 'phone' => null, 'email' => null, 'whatsapp' => null, 'role' => null, 'consent_marketing' => false])->save();
            }
            foreach ($found['leads'] as $l) {
                $l->activities()->update(['body' => '[erased on request]']);
                $l->forceFill(['cargo' => null, 'ai_summary' => null])->save();
            }
            foreach ($found['customers'] as $cu) {
                $hasInvoices = $cu->invoices()->exists();
                $kept += $hasInvoices ? 1 : 0;
                // Name, billing address and invoices stay when tax law requires them; contact details always go.
                $cu->forceFill(['phone' => null, 'email' => null, 'notes' => null]
                    + ($hasInvoices ? [] : ['name' => 'Erased customer #'.$cu->id, 'billing_address' => null]))->save();
            }
            foreach ($found['applications'] as $a) {
                $a->delete();
            }
            foreach ($found['chats'] as $ch) {
                $ch->messages()->delete();
                $ch->forceFill(['visitor_name' => null, 'visitor_phone' => null, 'visitor_email' => null, 'ip_hash' => null, 'summary' => null, 'handoff_reason' => null, 'status' => 'closed'])->save();
            }
            foreach ($found['subscriptions'] as $s) {
                $s->delete();
            }
        });

        $summary = $this->countsOf($found) + ['customers_kept_for_tax_law' => $kept];
        $this->record('erase', $input, $by, $summary);

        return $summary;
    }

    public static function mask(string $input): string
    {
        ['email' => $email, 'phone' => $phone] = self::identify($input);
        if ($email !== null) {
            [$user, $domain] = explode('@', $email, 2);

            return mb_substr($user, 0, 1).str_repeat('*', max(2, mb_strlen($user) - 1)).'@'.$domain;
        }

        return substr((string) $phone, 0, 6).'****'.substr((string) $phone, -3);
    }

    /**
     * @param  array<string, Collection<int, mixed>>  $found
     * @return array<string, int>
     */
    private function countsOf(array $found): array
    {
        return array_map(fn (Collection $c): int => $c->count(), $found);
    }

    /** @param  array<string, int|string>  $summary */
    private function record(string $type, string $input, User $by, array $summary): void
    {
        $id = self::identify($input);
        PrivacyRequest::query()->create([
            'type' => $type, 'subject_masked' => self::mask($input),
            'subject_hash' => hash('sha256', ($id['email'] ?? $id['phone']).config('app.key')),
            'summary' => $summary, 'handled_by' => $by->id,
        ]);
        activity()->causedBy($by)->withProperties(['subject' => self::mask($input)] + $summary)->log(ucfirst($type).' of personal data on request');
    }
}
