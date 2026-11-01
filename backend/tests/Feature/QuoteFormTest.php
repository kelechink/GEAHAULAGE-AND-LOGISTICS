<?php

declare(strict_types=1);

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Mail\NewLeadMail;
use App\Mail\QuoteReceivedMail;
use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

function quotePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Obi',
        'company' => 'Obi Foods Ltd',
        'phone' => '0803 123 4567',
        'email' => 'ada@obifoods.ng',
        'from' => 'Apapa, Lagos',
        'to' => 'Kano',
        'service' => 'Container haulage',
        'details' => 'One 40ft container, around 25 tonnes, next week.',
    ], $overrides);
}

beforeEach(fn () => Mail::fake());

it('turns a quote request into a CRM lead with a follow-up', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-06 10:00', 'Africa/Lagos')); // a Friday

    $this->from('/')->post('/quote', quotePayload())
        ->assertRedirectContains('#quote')
        ->assertSessionHas('quote_sent', 'Ada');

    $lead = Lead::query()->with('contact', 'company', 'followUps', 'activities')->sole();

    expect($lead->source)->toBe(LeadSource::WebsiteForm)
        ->and($lead->stage)->toBe(LeadStage::New)
        ->and($lead->origin)->toBe('Apapa, Lagos')
        ->and($lead->destination)->toBe('Kano')
        ->and($lead->contact->phone)->toBe('2348031234567')
        ->and($lead->company?->name)->toBe('Obi Foods Ltd')
        ->and($lead->activities)->toHaveCount(1)
        ->and($lead->followUps)->toHaveCount(1)
        // Next working day after a Friday is Monday.
        ->and($lead->next_follow_up_at?->timezone('Africa/Lagos')->format('D'))->toBe('Mon');

    Carbon::setTestNow();
});

it('emails sales and sends the customer an auto-reply', function () {
    $this->post('/quote', quotePayload());

    Mail::assertQueued(NewLeadMail::class, fn ($m) => $m->hasTo('sales@geahaulageandlogistics.com'));
    Mail::assertQueued(QuoteReceivedMail::class, fn ($m) => $m->hasTo('ada@obifoods.ng'));
});

it('skips the auto-reply when no email is given', function () {
    $this->post('/quote', quotePayload(['email' => '']));

    Mail::assertQueued(NewLeadMail::class);
    Mail::assertNotQueued(QuoteReceivedMail::class);
});

it('matches a returning customer by phone instead of creating a duplicate', function () {
    $this->post('/quote', quotePayload());
    $this->post('/quote', quotePayload(['phone' => '+234 803 123 4567', 'email' => '']));

    expect(Contact::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(2);
});

it('explains what is missing', function () {
    $this->from('/quote')->post('/quote', quotePayload(['name' => '', 'phone' => 'abc', 'from' => '']))
        ->assertRedirect('/quote')
        ->assertSessionHasErrors([
            'name' => 'Enter your full name.',
            'phone' => 'Enter a phone number like 0816 216 3235.',
            'from' => 'Enter the pickup location.',
        ]);

    expect(Lead::query()->count())->toBe(0);
});

it('rejects bots that fill the hidden honeypot field', function () {
    $this->post('/quote', quotePayload(['website' => 'http://spam.example']))
        ->assertSessionHasErrors('website');

    expect(Lead::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('limits each visitor to 5 form submissions a minute', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/quote', quotePayload(['phone' => '080312345'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]))->assertRedirect();
    }

    $this->post('/quote', quotePayload())->assertStatus(429);
});
