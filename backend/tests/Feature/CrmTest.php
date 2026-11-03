<?php

declare(strict_types=1);

use App\Domain\Crm\ActivityLogger;
use App\Domain\Crm\LeadCaptureService;
use App\Domain\Crm\LeadPipeline;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\Role;
use App\Filament\Pages\Pipeline;
use App\Filament\Resources\LeadResource\Pages\ListLeads;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\Customer;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

function makeLead(array $overrides = []): Lead
{
    return app(LeadCaptureService::class)->captureQuoteRequest(array_merge([
        'name' => 'Ada Obi', 'phone' => '08031234567', 'company' => 'Obi Foods Ltd', 'from' => 'Apapa', 'to' => 'Kano',
    ], $overrides));
}

it('assigns new leads to sales staff in turn', function () {
    $a = $this->userWithRole(Role::Sales);
    $b = $this->userWithRole(Role::Sales);
    $this->userWithRole(Role::Sales, ['is_active' => false]);

    $owners = collect(range(1, 4))->map(fn ($i) => makeLead(['phone' => '0803123456'.$i])->owner_user_id);

    expect($owners->all())->toBe([$a->id, $b->id, $a->id, $b->id])
        ->and(FollowUp::query()->pluck('assigned_to')->all())->toBe([$a->id, $b->id, $a->id, $b->id]);
});

it('logs every stage change and records first contact', function () {
    $lead = makeLead();
    $user = $this->userWithRole(Role::Sales);

    app(LeadPipeline::class)->move($lead, LeadStage::Contacted, $user);

    expect($lead->fresh()->stage)->toBe(LeadStage::Contacted)
        ->and($lead->fresh()->first_contacted_at)->not->toBeNull()
        ->and($lead->activities()->where('type', 'stage_change')->first()?->body)->toBe('Moved from New to Contacted.');
});

it('needs a reason to mark a lead as lost, and closes its follow-ups', function () {
    $lead = makeLead();

    expect(fn () => app(LeadPipeline::class)->move($lead, LeadStage::Lost))->toThrow(InvalidArgumentException::class);

    app(LeadPipeline::class)->move($lead, LeadStage::Lost, null, 'Chose another haulier');

    expect($lead->fresh()->lost_reason)->toBe('Chose another haulier')
        ->and($lead->followUps()->where('status', 'open')->count())->toBe(0);
});

it('turns a won lead into a customer, reusing the company if it is already a customer', function () {
    $first = makeLead();
    app(LeadPipeline::class)->move($first, LeadStage::Won);

    $customer = $first->fresh()->customer;
    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Obi Foods Ltd')
        ->and($customer->phone)->toBe('2348031234567');

    $second = makeLead(['name' => 'Another Person', 'phone' => '08099999999']);
    app(LeadPipeline::class)->move($second, LeadStage::Won);

    expect($second->fresh()->customer_id)->toBe($customer->id)
        ->and(Customer::query()->count())->toBe(1);
});

it('counts a call as first contact but not a note', function () {
    $lead = makeLead();
    $logger = app(ActivityLogger::class);

    $logger->log($lead, 'note', 'Looked up their address');
    expect($lead->fresh()->first_contacted_at)->toBeNull();

    $logger->log($lead, 'call', 'Discussed the load');
    expect($lead->fresh()->first_contacted_at)->not->toBeNull();
});

it('marks follow-ups missed once they are a day overdue', function () {
    $lead = makeLead();
    $lead->followUps()->update(['due_at' => now()->subDays(2)]);

    $this->artisan('crm:mark-missed-follow-ups')->assertSuccessful();

    expect($lead->followUps()->first()?->status)->toBe('missed');
});

it('lets sales quick-add a phone enquiry with three fields', function () {
    $this->actingAs($this->userWithRole(Role::Sales));

    Livewire::test(ListLeads::class)
        ->callAction('quickAdd', ['name' => 'Walk In Customer', 'phone' => '08055554444', 'source' => 'walk_in'])
        ->assertHasNoActionErrors();

    $lead = Lead::query()->sole();
    expect($lead->source)->toBe(LeadSource::WalkIn)
        ->and($lead->followUps()->count())->toBe(1);
});

it('moves leads on the pipeline board and asks why when a lead is lost', function () {
    $this->actingAs($this->userWithRole(Role::Sales));
    $lead = makeLead();

    Livewire::test(Pipeline::class)
        ->call('moveLead', $lead->id, 'qualified')
        ->assertHasNoErrors();
    expect($lead->fresh()->stage)->toBe(LeadStage::Qualified);

    Livewire::test(Pipeline::class)
        ->call('moveLead', $lead->id, 'lost')
        ->assertActionMounted('markLost')
        ->setActionData(['reason' => 'No budget'])
        ->callMountedAction();
    expect($lead->fresh()->stage)->toBe(LeadStage::Lost)
        ->and($lead->fresh()->lost_reason)->toBe('No budget');
});

it('lets management see the pipeline but not move leads', function () {
    $this->actingAs($this->userWithRole(Role::Management));
    $lead = makeLead();

    $this->get('/admin/pipeline')->assertOk();
    Livewire::test(Pipeline::class)->call('moveLead', $lead->id, 'won')->assertForbidden();
});

it('opens every CRM screen for sales and none for operations', function (string $path) {
    $this->actingAs($this->userWithRole(Role::Sales))->get($path)->assertOk();
    $this->actingAs($this->userWithRole(Role::Operations))->get($path)->assertForbidden();
})->with(['/admin/leads', '/admin/pipeline', '/admin/follow-ups', '/admin/contacts', '/admin/companies', '/admin/customers', '/admin/crm-reports']);

it('shows a lead with its activity and follow-ups', function () {
    $this->actingAs($this->userWithRole(Role::Sales));
    $lead = makeLead();

    $this->get("/admin/leads/{$lead->id}")->assertOk()->assertSee('Ada Obi')->assertSee('Follow-ups');
});
