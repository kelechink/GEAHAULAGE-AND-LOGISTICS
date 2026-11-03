<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Crm\LeadCaptureService;
use App\Domain\Crm\PhoneNumber;
use App\Domain\Tracking\EtaEstimator;
use App\Enums\LeadSource;
use App\Enums\Permission;
use App\Models\Ai\Conversation;
use App\Models\Crm\Lead;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\AiHandoffNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Function tools the model may call (PRD 12.4). Laravel runs them; results contain public-safe fields only.
 */
final class AssistantTools
{
    public function __construct(
        private readonly KnowledgeBase $kb,
        private readonly LeadCaptureService $leads,
    ) {}

    /** @return list<array{name: string, description: string, parameters: array<string, mixed>}> */
    public function definitions(): array
    {
        $str = ['type' => 'string'];

        return [
            ['name' => 'track_shipment', 'description' => 'Look up a shipment by its tracking code (like GEA-2611-AB3CD). Returns stage, city-level location, last update and ETA window.',
                'parameters' => ['type' => 'object', 'properties' => ['code' => $str], 'required' => ['code']]],
            ['name' => 'create_or_update_lead', 'description' => 'Save a quote request for the sales team. First read the details back to the visitor and get a clear yes, then call with confirmed=true.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'name' => $str, 'phone' => $str, 'email' => $str, 'service' => $str, 'origin' => $str, 'destination' => $str,
                    'cargo' => $str, 'tonnage' => $str, 'date' => ['type' => 'string', 'description' => 'Pickup date, YYYY-MM-DD if known'],
                    'confirmed' => ['type' => 'boolean', 'description' => 'true only after the visitor confirmed the details'],
                ], 'required' => ['name', 'phone', 'confirmed']]],
            ['name' => 'request_callback', 'description' => 'Ask the sales team to phone the visitor back.',
                'parameters' => ['type' => 'object', 'properties' => ['name' => $str, 'phone' => $str, 'preferred_time' => $str], 'required' => ['name', 'phone']]],
            ['name' => 'handoff_to_human', 'description' => 'Pass the chat to a GEA team member. Use for complaints, damage or loss, payments, disputes, anything you cannot answer from the knowledge, or when the visitor asks for a person.',
                'parameters' => ['type' => 'object', 'properties' => ['reason' => $str], 'required' => ['reason']]],
            ['name' => 'get_faq', 'description' => 'Fetch GEA knowledge on a topic (services, fleet, coverage, process, contact, careers).',
                'parameters' => ['type' => 'object', 'properties' => ['topic' => $str], 'required' => ['topic']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function run(string $name, array $args, Conversation $c): array
    {
        return match ($name) {
            'track_shipment' => $this->track((string) ($args['code'] ?? '')),
            'create_or_update_lead' => $this->lead($args, $c),
            'request_callback' => $this->callback($args, $c),
            'handoff_to_human' => $this->handoff((string) ($args['reason'] ?? 'Visitor asked for a person'), $c),
            'get_faq' => $this->faq((string) ($args['topic'] ?? '')),
            default => ['error' => 'Unknown tool.'],
        };
    }

    /** @return array<string, mixed> */
    public function track(string $code): array
    {
        $code = strtoupper(trim(preg_replace('/\s+/', '', $code) ?? ''));
        $s = Shipment::query()->with('latestPosition')->where('tracking_code', $code)->first();
        if ($s === null) {
            return ['found' => false, 'message' => 'No shipment with that code. Codes look like GEA-2611-AB3CD and are on the waybill or SMS.'];
        }
        $place = $s->latestPosition?->place_label;
        $eta = app(EtaEstimator::class)->estimate($s);

        // Public-safe fields only (PRD 12.1): stage, city-level location, last update, ETA.
        return [
            'found' => true,
            'code' => $s->tracking_code,
            'route' => $s->origin.' to '.$s->destination,
            'stage' => $s->stage->label(),
            'near' => $place !== null ? explode(',', $place)[0] : null,
            'last_update' => $s->last_update_at?->diffForHumans(),
            'eta' => EtaEstimator::describe($eta),
            'tracking_page' => route('track.show', $s->tracking_code),
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    private function lead(array $a, Conversation $c): array
    {
        if (($a['confirmed'] ?? false) !== true) {
            return ['saved' => false, 'message' => 'Not saved. Read the details back to the visitor and ask them to confirm first.'];
        }
        $phone = PhoneNumber::normalise((string) ($a['phone'] ?? ''));
        if ($phone === null || strlen($phone) < 13 || blank($a['name'] ?? null)) {
            return ['saved' => false, 'message' => 'A name and a valid Nigerian phone number are needed.'];
        }

        $data = array_filter([
            'name' => trim((string) $a['name']), 'phone' => $phone, 'email' => $a['email'] ?? null, 'service' => $a['service'] ?? null,
            'from' => $a['origin'] ?? null, 'to' => $a['destination'] ?? null, 'details' => $a['cargo'] ?? null,
        ], fn ($v): bool => filled($v));

        $lead = self::isOpen($c->lead) ? $c->lead : $this->leads->captureQuoteRequest($data, LeadSource::AiChat);
        $date = null;
        if (filled($a['date'] ?? null)) {
            try {
                $date = Carbon::parse((string) $a['date'])->toDateString();
            } catch (\Throwable) {
                $date = null;
            }
        }
        $lead->fill(array_filter([
            'service_interest' => $a['service'] ?? null, 'origin' => $a['origin'] ?? null, 'destination' => $a['destination'] ?? null,
            'cargo' => $a['cargo'] ?? null, 'tonnage' => isset($a['tonnage']) ? (preg_replace('/[^\d.]/', '', (string) $a['tonnage']) ?: null) : null,
            'preferred_date' => $date,
        ], fn ($v): bool => filled($v)));
        $lead->intent_score = IntentScore::forLead($lead, $c);
        $lead->ai_summary = IntentScore::summary($lead);
        $lead->save();

        $lead->activities()->create(['contact_id' => $lead->contact_id, 'type' => 'note', 'at' => now(),
            'body' => "Website chat transcript:\n".$c->load('messages')->transcript()]);

        $c->forceFill(['lead_id' => $lead->id, 'visitor_name' => $data['name'], 'visitor_phone' => $phone,
            'visitor_email' => $data['email'] ?? $c->visitor_email])->save();

        return ['saved' => true, 'message' => 'Saved. The sales team replies within one working day.'];
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    private function callback(array $a, Conversation $c): array
    {
        $phone = PhoneNumber::normalise((string) ($a['phone'] ?? ''));
        if ($phone === null || strlen($phone) < 13) {
            return ['saved' => false, 'message' => 'A valid Nigerian phone number is needed.'];
        }
        $when = trim((string) ($a['preferred_time'] ?? ''));
        $lead = self::isOpen($c->lead) ? $c->lead : $this->leads->quickAdd(trim((string) ($a['name'] ?? 'Website visitor')), $phone, LeadSource::AiChat, 'Asked for a call back from the website chat.');
        $lead->followUps()->create([
            'due_at' => now()->addHour(),
            'assigned_to' => $lead->owner_user_id,
            'note' => 'Call back requested in the website chat'.($when !== '' ? " (preferred time: {$when})" : '').'.',
        ]);
        $c->forceFill(['lead_id' => $lead->id, 'visitor_name' => $a['name'] ?? $c->visitor_name, 'visitor_phone' => $phone])->save();

        return ['saved' => true, 'message' => 'Call back booked. A GEA sales officer will phone '.($when !== '' ? $when : 'soon, within office hours').'.'];
    }

    /** @return array<string, mixed> */
    public function handoff(string $reason, Conversation $c): array
    {
        if (! $c->isHuman()) {
            $c->forceFill(['status' => 'needs_human', 'handoff_reason' => mb_substr($reason, 0, 250)])->save();
            $staff = User::query()->permission(Permission::AiInbox->value)->where('is_active', true)->get();
            Notification::send($staff, new AiHandoffNotification($c));
        }

        return ['handed_off' => true, 'message' => 'A GEA team member has been asked to join this chat.'];
    }

    /** @return array<string, mixed> */
    private function faq(string $topic): array
    {
        $item = $this->kb->find($topic);

        return $item === null ? ['found' => false] : ['found' => true, 'title' => $item->title, 'answer' => $item->body];
    }

    /** True when the lead is still open (used to keep updates on the same lead). */
    public static function isOpen(?Lead $lead): bool
    {
        return $lead !== null && ! in_array($lead->stage->value, ['won', 'lost'], true);
    }
}
