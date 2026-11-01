<?php

declare(strict_types=1);

use App\Domain\Ai\AskTheSystem;
use App\Domain\Ai\DailyDigest;
use App\Domain\Ai\DataGuard;
use App\Domain\Ai\Inbox;
use App\Domain\Crm\LeadCaptureService;
use App\Domain\Shipping\ShipmentService;
use App\Enums\LeadSource;
use App\Enums\Role;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Filament\Resources\AiConversationResource\Pages\ViewAiConversation;
use App\Mail\DailyDigestMail;
use App\Models\Ai\Conversation;
use App\Models\Ai\Digest;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\Finance\Invoice;
use App\Models\Shipment;
use App\Notifications\AiHandoffNotification;
use App\Notifications\AiOfflineNotification;
use App\Notifications\OpsAlert;
use Database\Seeders\KnowledgeSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const GEMINI = 'generativelanguage.googleapis.com/*';
const GROQ = 'api.groq.com/*';

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    config(['services.gemini.key' => 'test-gemini', 'services.groq.key' => 'test-groq']);
    $this->seed(KnowledgeSeeder::class);
});

function geminiText(string $text): array
{
    return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]], 'usageMetadata' => ['totalTokenCount' => 50]];
}

function geminiCall(string $name, array $args): array
{
    return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => ['name' => $name, 'args' => $args]]]]]]];
}

function chat(string $message, ?string $token = null, string $page = '/'): array
{
    $res = test()->postJson('/api/assistant/message', ['message' => $message, 'token' => $token, 'page' => $page])->assertOk()->json();
    // Keep only what came back after this visitor message: that is what the assistant just said.
    $all = $res['messages'];
    $lastVisitor = (int) collect($all)->keys()->filter(fn (int $i): bool => $all[$i]['role'] === 'visitor')->last();
    $res['messages'] = array_values(array_slice($all, $lastVisitor + 1));

    return $res;
}

it('answers from the knowledge base through Gemini and counts usage', function () {
    Http::fake([GEMINI => Http::response(geminiText('We run 35 trucks from 5 to 30 tonnes. Want a quote?'))]);

    $res = chat('How big are your trucks?');

    expect($res['messages'])->toHaveCount(1)
        ->and($res['messages'][0]['body'])->toContain('35 trucks')
        ->and(strlen($res['token']))->toBe(40);
    Http::assertSent(function (Request $r): bool {
        $system = (string) data_get($r->data(), 'systemInstruction.parts.0.text');

        return $r->hasHeader('x-goog-api-key', 'test-gemini')
            && str_contains($system, 'Fleet and capacity') && str_contains($system, 'Never invent prices');
    });
    expect(DB::table('ai_usage')->where('provider', 'gemini')->value('requests'))->toBe(1);
});

it('falls back to Groq when Gemini is rate-limited', function () {
    Http::fake([
        GEMINI => Http::response(['error' => 'quota'], 429),
        GROQ => Http::response(['choices' => [['message' => ['content' => 'Yes, we haul containers.']]], 'usage' => ['total_tokens' => 30]]),
    ]);

    expect(chat('Do you move containers?')['messages'][0]['body'])->toBe('Yes, we haul containers.');
    expect(DB::table('ai_usage')->where('provider', 'gemini')->value('failures'))->toBe(1);
});

it('offers the lead form and alerts Management once when both providers are down', function () {
    Notification::fake();
    Http::fake([GEMINI => Http::response([], 429), GROQ => Http::response([], 503)]);
    $md = $this->userWithRole(Role::Management);

    $first = chat('Hello there');
    chat('Anyone?', $first['token']);

    expect($first['messages'][0]['meta']['form'])->toBe('lead');
    Notification::assertSentToTimes($md, AiOfflineNotification::class, 1);
});

it('never sends money amounts or account numbers to the AI', function () {
    Http::fake([GEMINI => Http::response(geminiText('Thanks. A quote needs your route and cargo.'))]);

    chat('My budget is ₦250,000 or N 1.5m, my account is 0123456789 and card 4111 1111 1111 1111');

    Http::assertSent(function (Request $r): bool {
        $body = json_encode($r->data());

        return ! str_contains($body, '250,000') && ! str_contains($body, '0123456789') && ! str_contains($body, '4111')
            && str_contains($body, '[amount]') && str_contains($body, '[number]');
    });
    expect(DataGuard::scrub('Call me on 08031234567'))->toContain('08031234567'); // the visitor's own phone is kept
});

it('tracks a shipment with public-safe fields only', function () {
    $s = Shipment::factory()->create(['stage' => 'in_transit', 'notes' => 'SECRET 950000', 'last_update_at' => now()]);
    Http::fake([GEMINI => Http::sequence()
        ->push(geminiCall('track_shipment', ['code' => strtolower($s->tracking_code)]))
        ->push(geminiText('Your shipment is in transit.'))]);

    chat("Where is {$s->tracking_code}?");

    $recorded = Http::recorded();
    expect($recorded)->toHaveCount(2);
    $toolResult = json_encode(data_get($recorded[1][0]->data(), 'contents'));
    expect($toolResult)->toContain('In transit')->toContain($s->tracking_code)
        ->not->toContain('SECRET')->not->toContain('950000');
});

it('saves a chat lead only after the visitor confirms, with transcript and intent score', function () {
    $this->userWithRole(Role::Sales);
    $args = ['name' => 'Ada Obi', 'phone' => '0803 123 4567', 'origin' => 'Apapa', 'destination' => 'Kano', 'cargo' => 'Rice',
        'tonnage' => '30', 'date' => now()->addDays(3)->toDateString()];
    Http::fake([GEMINI => Http::sequence()
        ->push(geminiCall('create_or_update_lead', $args + ['confirmed' => false]))
        ->push(geminiText('Please confirm: Ada Obi, 0803 123 4567, Apapa to Kano, 30 t rice. Correct?'))
        ->push(geminiCall('create_or_update_lead', $args + ['confirmed' => true]))
        ->push(geminiText('Saved! Our team will call you within one working day.'))]);

    $r = chat('I need 30 tonnes of rice moved from Apapa to Kano. Ada Obi, 0803 123 4567');
    expect(Lead::query()->count())->toBe(0);
    chat('Yes, correct', $r['token']);

    $lead = Lead::query()->with('activities')->sole();
    expect($lead->source)->toBe(LeadSource::AiChat)
        ->and($lead->intent_score)->toBeGreaterThanOrEqual(70)
        ->and($lead->activities->pluck('body')->implode(' '))->toContain('Website chat transcript')
        ->and(Conversation::query()->sole()->lead_id)->toBe($lead->id);
});

it('hands complaints to a person without calling the AI, and staff can take over', function () {
    Notification::fake();
    Http::fake();
    $sales = $this->userWithRole(Role::Sales);

    $r = chat('My goods were damaged and I want a refund');
    $c = Conversation::query()->sole();

    expect($c->status)->toBe('needs_human');
    Http::assertNothingSent();
    Notification::assertSentTo($sales, AiHandoffNotification::class);

    app(Inbox::class)->reply($c, $sales, 'Hello, this is Sales. I am sorry, let me help.');
    chat('Thank you', $r['token']);
    Http::assertNothingSent(); // staff are answering, so no AI

    $poll = $this->getJson("/api/assistant/{$r['token']}?after=0")->assertOk()->json();
    $bodies = collect($poll['messages'])->pluck('body');
    expect($poll['status'])->toBe('human')
        ->and($bodies)->toContain('A GEA team member has joined the chat.')
        ->and($bodies)->toContain('Hello, this is Sales. I am sorry, let me help.');
});

it('stops at 20 turns and serves repeated first questions from cache', function () {
    Http::fake([GEMINI => Http::response(geminiText('We move goods across Nigeria.'))]);

    chat('Where do you deliver?');
    chat('Where do you deliver?');
    Http::assertSentCount(1);

    $c = Conversation::query()->latest('id')->first();
    $c->forceFill(['turns' => 20])->save();
    $last = chat('One more question', $c->token);
    expect($last['messages'][0]['body'])->toContain('limit')->and($last['messages'][0]['meta']['form'])->toBe('lead');
    Http::assertSentCount(1);
});

it('takes a lead from the offline form', function () {
    Http::fake([GEMINI => Http::response([], 500), GROQ => Http::response([], 500)]);
    $r = chat('hello');

    $this->postJson('/api/assistant/lead', ['token' => $r['token'], 'name' => 'Tunde', 'phone' => '08031112222', 'details' => 'Tipper to Ibadan'])->assertOk();
    $this->postJson('/api/assistant/lead', ['name' => 'X', 'phone' => '123'])->assertStatus(422);

    expect(Lead::query()->sole()->source)->toBe(LeadSource::AiChat)
        ->and(Conversation::query()->sole()->visitor_phone)->toBe('2348031112222');
});

it('builds the morning digest without sending money to the AI', function () {
    Shipment::factory()->create(['stage' => 'delayed', 'delay_reason' => 'Flooding at Lokoja']);
    Invoice::query()->create(['customer_id' => Customer::factory()->create()->id, 'status' => 'overdue', 'template' => 'corporate_classic',
        'issue_date' => now()->subDays(40), 'due_date' => now()->subDays(10), 'subtotal_kobo' => 12_345_600, 'total_kobo' => 12_345_600, 'balance_kobo' => 12_345_600]);
    Http::fake([GEMINI => Http::response(geminiText('One trip is delayed by flooding at Lokoja and one invoice is overdue.'))]);
    $md = $this->userWithRole(Role::Management);
    $ops = $this->userWithRole(Role::Operations);

    $this->artisan('ai:daily-digest')->assertSuccessful();

    $d = Digest::query()->sole();
    expect($d->facts['overdue_invoices'])->toBe(1)->and($d->facts['receivable_kobo'])->toBe(12_345_600);
    Http::assertSent(fn (Request $r): bool => ! str_contains(json_encode($r->data()), '12345600') && ! str_contains(json_encode($r->data()), '123,456'));
    Mail::assertQueued(DailyDigestMail::class, 2);
    // Money totals are added by Laravel, and only for people who may see finance.
    Mail::assertQueued(DailyDigestMail::class, fn (DailyDigestMail $m): bool => $m->hasTo($md->email) && $m->withMoney);
    Mail::assertQueued(DailyDigestMail::class, fn (DailyDigestMail $m): bool => $m->hasTo($ops->email) && ! $m->withMoney);

    config(['services.gemini.key' => null, 'services.groq.key' => null]); // AI offline: plain digest from the facts
    expect(app(DailyDigest::class)->build()->summary)->toContain('1 invoices are overdue');
});

it('answers "ask the system" with a whitelisted report and keeps figures away from the AI', function () {
    Http::fake([GEMINI => Http::response(geminiCall('customers_owing', ['min_naira' => 50000]))]);
    $cust = Customer::factory()->create(['name' => 'Big Debtor Ltd']);
    Invoice::query()->create(['customer_id' => $cust->id, 'status' => 'issued', 'template' => 'corporate_classic', 'issue_date' => now(),
        'due_date' => now()->addDays(30), 'subtotal_kobo' => 80_000_00, 'total_kobo' => 80_000_00, 'balance_kobo' => 80_000_00]);

    $a = app(AskTheSystem::class)->ask('List customers owing more than ₦50k');

    expect($a['rows'][0][0])->toBe('Big Debtor Ltd')->and($a['rows'][0][1])->toContain('80,000');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r): bool => ! str_contains(json_encode($r->data()), 'Big Debtor'));

    expect(app(AskTheSystem::class)->keywordRoute('which trucks made the most money in october'))
        ->toBe(['trucks_by_profit', ['month' => now()->year.'-10']]);
});

it('raises rule-based alerts once for delays, overdue invoices and uncontacted leads', function () {
    Notification::fake();
    $ops = $this->userWithRole(Role::Operations);
    $fin = $this->userWithRole(Role::Finance);
    $md = $this->userWithRole(Role::Management);

    $s = Shipment::factory()->create(['stage' => 'in_transit']);
    app(ShipmentService::class)->transition($s, ShipmentStage::Delayed, TrackingSource::Manual, $ops, 'Police checkpoint');
    Notification::assertSentTo($ops, OpsAlert::class, fn (OpsAlert $n): bool => str_contains($n->title, 'delayed'));

    Invoice::query()->create(['customer_id' => Customer::factory()->create()->id, 'status' => 'overdue', 'template' => 'corporate_classic',
        'issue_date' => now()->subDays(40), 'due_date' => now()->subDays(3), 'subtotal_kobo' => 100, 'total_kobo' => 100, 'balance_kobo' => 100]);
    $lead = app(LeadCaptureService::class)->quickAdd('Old Lead', '08030000001', LeadSource::Phone);
    $lead->forceFill(['created_at' => now()->subDays(4)])->save();

    $this->artisan('alerts:sweep')->expectsOutputToContain('1 overdue and 1 uncontacted')->assertSuccessful();
    $this->artisan('alerts:sweep')->expectsOutputToContain('0 overdue and 0 uncontacted');
    Notification::assertSentTo($fin, OpsAlert::class, fn (OpsAlert $n): bool => str_contains($n->title, 'overdue'));
    Notification::assertSentTo($md, OpsAlert::class, fn (OpsAlert $n): bool => str_contains($n->title, 'not contacted'));
});

it('shows the chat bubble on public pages and the inbox to staff', function () {
    $this->get('/')->assertOk()->assertSee('id="gea-ai"', false)->assertSee('js/assistant.js', false)
        ->assertSee('Chats are saved to help our team respond.');

    $c = Conversation::query()->create(['token' => str_repeat('a', 40), 'status' => 'needs_human', 'handoff_reason' => 'Complaint']);
    $c->messages()->create(['role' => 'visitor', 'body' => 'Help please']);
    $sales = $this->userWithRole(Role::Sales);
    $this->actingAs($sales)->get('/admin/ai-conversations')->assertOk()->assertSee('Needs a person');
    Livewire::actingAs($sales)->test(ViewAiConversation::class, ['record' => $c->id])
        ->set('reply', 'On it')->call('send')->assertHasNoErrors();
    expect($c->refresh()->status)->toBe('human')->and($c->assigned_user_id)->toBe($sales->id);

    $this->actingAs($this->userWithRole(Role::Operations))->get('/admin/ai-conversations')->assertForbidden();
    $this->actingAs($this->userWithRole(Role::Management))->get('/admin/ask')->assertOk();
    $this->actingAs($sales)->get('/admin/ask')->assertForbidden();
    $this->actingAs($this->userWithRole(Role::Management))->get('/admin/knowledge-items')->assertOk()->assertSee('Fleet and capacity');
});
