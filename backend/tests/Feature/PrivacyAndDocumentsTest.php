<?php

declare(strict_types=1);

use App\Domain\Crm\LeadCaptureService;
use App\Domain\Documents\DocumentLinks;
use App\Domain\Privacy\PersonalData;
use App\Domain\Shipping\ShipmentService;
use App\Enums\LeadSource;
use App\Enums\Role;
use App\Filament\Pages\DataRequests;
use App\Mail\InvoiceMail;
use App\Mail\ShipmentBookedMail;
use App\Models\Ai\Conversation;
use App\Models\Crm\Contact;
use App\Models\Customer;
use App\Models\Finance\Invoice;
use App\Models\JobApplication;
use App\Models\Privacy\PrivacyRequest;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use App\Models\TrackingSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
});

function personWithData(): array
{
    $lead = app(LeadCaptureService::class)->captureQuoteRequest(['name' => 'Ada Obi', 'phone' => '0803 123 4567', 'email' => 'ada@obifoods.ng',
        'from' => 'Apapa', 'to' => 'Kano', 'details' => '30t rice'], LeadSource::WebsiteForm);
    $customer = Customer::factory()->create(['name' => 'Ada Obi Ventures', 'email' => 'ada@obifoods.ng', 'phone' => '2348031234567', 'notes' => 'Prefers mornings']);
    JobApplication::query()->create(['name' => 'Ada Obi', 'phone' => '2348031234567', 'role' => 'Driver', 'about' => 'Ten years on the road']);
    $chat = Conversation::query()->create(['token' => str_repeat('b', 40), 'visitor_email' => 'ada@obifoods.ng', 'visitor_name' => 'Ada']);
    $chat->messages()->create(['role' => 'visitor', 'body' => 'My number is 08031234567']);
    TrackingSubscription::query()->create(['shipment_id' => Shipment::factory()->create(['customer_id' => $customer->id])->id, 'email' => 'ada@obifoods.ng']);

    return [$lead, $customer];
}

it('finds and exports everything held about a person', function () {
    personWithData();
    $md = $this->userWithRole(Role::Management);
    $data = app(PersonalData::class);

    expect($data->counts('ADA@obifoods.ng'))->toMatchArray(['contacts' => 1, 'customers' => 1, 'leads' => 1, 'chats' => 1, 'subscriptions' => 1])
        ->and($data->counts('08031234567')['applications'])->toBe(1);

    $path = $data->export('ada@obifoods.ng', $md);
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($path));
    $json = json_decode((string) $zip->getFromName('data.json'), true);
    $zip->close();
    expect($json['contacts'][0]['name'])->toBe('Ada Obi')
        ->and($json['website_chats'][0]['messages'][0]['text'])->toContain('08031234567')
        ->and(PrivacyRequest::query()->sole()->subject_masked)->toBe('a**@obifoods.ng');
    Storage::disk('local')->delete($path);
});

it('erases personal data but keeps invoices and the customer name tax law needs', function () {
    [$lead, $customer] = personWithData();
    Invoice::query()->create(['customer_id' => $customer->id, 'status' => 'issued', 'template' => 'corporate_classic', 'issue_date' => now(),
        'due_date' => now()->addDays(30), 'subtotal_kobo' => 100, 'total_kobo' => 100, 'balance_kobo' => 100]);
    $md = $this->userWithRole(Role::Management);

    $summary = app(PersonalData::class)->erase('ada@obifoods.ng', $md);
    app(PersonalData::class)->erase('08031234567', $md);

    $contact = Contact::query()->sole();
    expect($contact->email)->toBeNull()->and($contact->phone)->toBeNull()->and($contact->name)->toStartWith('Erased contact')
        ->and($lead->activities()->pluck('body')->unique()->all())->toBe(['[erased on request]'])
        ->and($customer->refresh()->name)->toBe('Ada Obi Ventures')->and($customer->email)->toBeNull()->and($customer->notes)->toBeNull()
        ->and($customer->invoices()->count())->toBe(1)
        ->and(JobApplication::query()->count())->toBe(0)
        ->and(Conversation::query()->sole()->messages()->count())->toBe(0)
        ->and(TrackingSubscription::query()->count())->toBe(0)
        ->and($summary['customers_kept_for_tax_law'])->toBe(1);
});

it('limits data requests to Management and needs ERASE typed to delete', function () {
    personWithData();
    $this->actingAs($this->userWithRole(Role::Sales))->get('/admin/data-requests')->assertForbidden();

    Livewire::actingAs($this->userWithRole(Role::Management))->test(DataRequests::class)
        ->set('subject', 'ada@obifoods.ng')->call('search')->assertSet('counts.contacts', 1)
        ->call('erase')->assertHasErrors('confirm')
        ->set('confirm', 'ERASE')->call('erase')->assertHasNoErrors();
    expect(Contact::query()->sole()->email)->toBeNull();
});

it('emails the customer a tracking link when a shipment is booked', function () {
    $customer = Customer::factory()->create(['email' => 'shipper@example.ng']);
    $s = app(ShipmentService::class)->create(['customer_id' => $customer->id, 'cargo_description' => 'Cement', 'tonnage' => 20, 'origin' => 'Apapa', 'destination' => 'Kano',
        'origin_lat' => 6.45, 'origin_lng' => 3.36, 'destination_lat' => 12.0, 'destination_lng' => 8.59]);

    Mail::assertQueued(ShipmentBookedMail::class, fn (ShipmentBookedMail $m): bool => $m->hasTo('shipper@example.ng')
        && str_contains($m->render(), $s->tracking_code) && str_contains($m->render(), route('track.show', $s->tracking_code))
        && $m->envelope()->from?->address === 'logistic@geahaulageandlogistics.com');

    $noEmail = Customer::factory()->create(['email' => null]);
    app(ShipmentService::class)->create(['customer_id' => $noEmail->id, 'cargo_description' => 'Rice', 'origin' => 'Apapa', 'destination' => 'Kano',
        'origin_lat' => 6.45, 'origin_lng' => 3.36, 'destination_lat' => 12.0, 'destination_lng' => 8.59]);
    Mail::assertQueuedCount(1);
});

it('serves invoices and proof of delivery only through valid signed links', function () {
    $invoice = Invoice::query()->create(['customer_id' => Customer::factory()->create()->id, 'number' => 'GEA-INV-2026-00077', 'status' => 'issued', 'template' => 'corporate_classic',
        'issue_date' => now(), 'due_date' => now()->addDays(30), 'subtotal_kobo' => 100, 'total_kobo' => 100, 'balance_kobo' => 100]);
    $link = DocumentLinks::invoice($invoice);

    $this->get($link)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('documents.invoice', $invoice))->assertForbidden();
    $other = $invoice->replicate()->fill(['number' => 'GEA-INV-2026-00078']);
    $other->save();
    $this->get(str_replace('/invoices/'.$invoice->id, '/invoices/'.$other->id, $link))->assertForbidden(); // tampered link
    $this->travel(31)->days();
    $this->get($link)->assertForbidden();
    $this->travelBack();

    Storage::fake('local');
    Storage::disk('local')->put('pod/x/photo.jpg', 'JPEGDATA');
    $proof = ProofOfDelivery::query()->create(['shipment_id' => Shipment::factory()->create()->id, 'receiver_name' => 'Musa', 'photo_path' => 'pod/x/photo.jpg', 'source' => 'driver_app']);
    expect(DocumentLinks::proof($proof, 'signature'))->toBeNull();
    $this->get((string) DocumentLinks::proof($proof, 'photo'))->assertOk();
    expect((new InvoiceMail($invoice))->render())->toContain('/documents/invoices/'.$invoice->id);
});
