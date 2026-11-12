<?php

declare(strict_types=1);

use App\Domain\Portal\PortalAccess;
use App\Enums\Role;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Mail\PortalInviteMail;
use App\Models\Customer;
use App\Models\Finance\Invoice;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

function portalCustomer(string $email = 'ada@obifoods.ng'): array
{
    $customer = Customer::factory()->create(['name' => 'Obi Foods Ltd']);
    $user = app(PortalAccess::class)->invite($customer, 'Ada Obi', $email);
    $user->forceFill(['password' => 'GoodPassword123'])->save();

    return [$customer, $user];
}

function issuedInvoice(Customer $c, string $number): Invoice
{
    return Invoice::query()->create(['customer_id' => $c->id, 'number' => $number, 'status' => 'issued', 'template' => 'corporate_classic',
        'issue_date' => now(), 'due_date' => now()->addDays(30), 'subtotal_kobo' => 5_000_000, 'total_kobo' => 5_375_000, 'balance_kobo' => 5_375_000]);
}

it('invites a customer contact by email with a link to choose a password', function () {
    $customer = Customer::factory()->create();
    Livewire::actingAs($this->userWithRole(Role::Sales))->test(ListCustomers::class)
        ->callTableAction('invitePortal', $customer, ['name' => 'Ada Obi', 'email' => 'Ada@ObiFoods.ng'])->assertHasNoTableActionErrors();

    $user = User::query()->where('email', 'ada@obifoods.ng')->sole();
    expect($user->hasRole(Role::Customer->value))->toBeTrue()->and($user->customer_id)->toBe($customer->id)
        ->and($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
    Mail::assertQueued(PortalInviteMail::class, fn (PortalInviteMail $m): bool => str_contains($m->link, '/portal/reset-password/')
        && $m->envelope()->from?->address === 'support@geahaulageandlogistics.com');

    $staff = $this->userWithRole(Role::Finance, ['email' => 'acc@example.test']);
    expect(fn () => app(PortalAccess::class)->invite($customer, 'X', 'acc@example.test'))->toThrow(InvalidArgumentException::class);
});

it('lets the invited customer set a password and sign in', function () {
    $customer = Customer::factory()->create();
    $user = app(PortalAccess::class)->invite($customer, 'Ada', 'ada@obifoods.ng');
    $token = Password::broker()->createToken($user);

    $this->post('/portal/reset-password', ['token' => $token, 'email' => 'ada@obifoods.ng', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post('/portal/reset-password', ['token' => $token, 'email' => 'ada@obifoods.ng', 'password' => 'GoodPassword123', 'password_confirmation' => 'GoodPassword123'])
        ->assertRedirect('/portal/login');

    $this->post('/portal/login', ['email' => 'ada@obifoods.ng', 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post('/portal/login', ['email' => 'ada@obifoods.ng', 'password' => 'GoodPassword123'])->assertRedirect('/portal');
    $this->get('/portal')->assertOk()->assertSee($customer->name);
});

it('shows a customer only their own shipments and invoices', function () {
    [$customer, $user] = portalCustomer();
    $mine = Shipment::factory()->create(['customer_id' => $customer->id, 'stage' => 'in_transit', 'origin' => 'Apapa', 'destination' => 'Kano']);
    $theirs = Shipment::factory()->create(['stage' => 'in_transit', 'origin' => 'Onitsha', 'destination' => 'Jos']);
    $myInvoice = issuedInvoice($customer, 'GEA-INV-2026-00101');
    $otherInvoice = issuedInvoice(Customer::factory()->create(), 'GEA-INV-2026-00102');
    Invoice::query()->create(['customer_id' => $customer->id, 'status' => 'draft', 'template' => 'corporate_classic', 'subtotal_kobo' => 1, 'total_kobo' => 1, 'balance_kobo' => 1]);

    $this->actingAs($user)->get('/portal')->assertOk()
        ->assertSee($mine->tracking_code)->assertDontSee($theirs->tracking_code)
        ->assertSee('GEA-INV-2026-00101')->assertDontSee('GEA-INV-2026-00102')->assertSee('₦53,750.00');

    $this->get("/portal/invoices/{$myInvoice->id}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get("/portal/invoices/{$otherInvoice->id}")->assertNotFound();
});

it('keeps guests, staff and switched-off customers out', function () {
    $this->get('/portal')->assertRedirect('/portal/login');
    $this->actingAs($this->userWithRole(Role::Operations))->get('/portal')->assertRedirect('/portal/login');
    auth()->logout();

    [, $user] = portalCustomer('off@example.ng');
    app(PortalAccess::class)->revoke($user);
    $this->post('/portal/login', ['email' => 'off@example.ng', 'password' => 'GoodPassword123'])->assertSessionHasErrors('email');
    $this->actingAs($user->refresh())->get('/portal')->assertRedirect('/portal/login');
});

it('sends portal password resets only to customer accounts', function () {
    Notification::fake();
    portalCustomer();
    $staff = $this->userWithRole(Role::Finance, ['email' => 'acc@example.test']);
    $this->travel(61)->seconds(); // Laravel allows one reset link a minute; the invite just made one

    $this->post('/portal/forgot-password', ['email' => 'ada@obifoods.ng'])->assertSessionHas('status');
    $this->post('/portal/forgot-password', ['email' => 'acc@example.test'])->assertSessionHas('status');

    Notification::assertSentTo(User::query()->where('email', 'ada@obifoods.ng')->sole(), ResetPassword::class);
    Notification::assertNotSentTo($staff, ResetPassword::class);
});

it('links the portal from the website header', function () {
    $this->get('/')->assertOk()->assertSee(route('portal.login'), false);
    $this->get('/portal/login')->assertOk()->assertSee('Customer portal');
});

it('shows the portal sign-in page to staff signed in to the back office, instead of the admin dashboard', function () {
    $this->actingAs($this->userWithRole(Role::Operations, ['name' => 'Ike Ops']))
        ->get('/portal/login')->assertOk()->assertSee('signed in to the staff back office as Ike Ops');
});

it('sends a signed-in customer from the sign-in page to their portal', function () {
    [, $user] = portalCustomer();
    $this->actingAs($user)->get('/portal/login')->assertRedirect('/portal');
});

it('never sends a customer to the admin after signing in, even if they opened /admin first', function () {
    portalCustomer();
    $this->get('/admin')->assertRedirect();
    $this->post('/portal/login', ['email' => 'ada@obifoods.ng', 'password' => 'GoodPassword123'])->assertRedirect('/portal');
});

it('returns a customer to the portal page they asked for after signing in', function () {
    [$customer] = portalCustomer();
    $invoice = issuedInvoice($customer, 'GEA-INV-0009');
    $this->get("/portal/invoices/{$invoice->id}")->assertRedirect('/portal/login');
    $this->post('/portal/login', ['email' => 'ada@obifoods.ng', 'password' => 'GoodPassword123'])->assertRedirect("/portal/invoices/{$invoice->id}");
});
