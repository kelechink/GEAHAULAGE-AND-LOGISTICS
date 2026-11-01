<?php

declare(strict_types=1);

use App\Domain\Finance\InvoiceService;
use App\Domain\Finance\PaymentService;
use App\Domain\Reporting\DashboardData;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Finance\Expense;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('counts money received and spent today in this month\'s figures', function () {
    $customer = Customer::factory()->create();
    $inv = app(InvoiceService::class)->saveDraft(null, ['customer_id' => $customer->id], [['description' => 'Haulage', 'quantity' => '1', 'unit_price_kobo' => 100_000_000]]);
    app(InvoiceService::class)->issue($inv);
    app(PaymentService::class)->record($customer, 50_000_000, PaymentMethod::BankTransfer, now());
    Expense::query()->create(['category' => 'fuel', 'spent_on' => now(), 'amount_kobo' => 20_000_000, 'description' => 'Diesel']);

    $d = app(DashboardData::class)->for($this->userWithRole(Role::Management));
    $kpi = collect($d['kpis'])->keyBy('key');

    expect($kpi['income']['full'])->toBe('₦500,000.00')
        ->and($kpi['expenses']['full'])->toBe('₦200,000.00')
        ->and($kpi['net']['full'])->toBe('₦300,000.00')
        ->and($kpi['income']['value'])->toBe('₦500.0k')
        ->and(end($d['charts']['cashflow']['income']))->toBe(500_000);
});

it('shows each role only the blocks it may see', function () {
    $data = app(DashboardData::class);

    expect(array_keys($data->for($this->userWithRole(Role::Management))['charts']))->toBe(['cashflow', 'requests', 'stages', 'expenses'])
        ->and(array_keys($data->for($this->userWithRole(Role::Sales))['charts']))->toBe(['requests'])
        ->and(array_keys($data->for($this->userWithRole(Role::Operations))['charts']))->toBe(['stages'])
        ->and(collect($data->for($this->userWithRole(Role::Sales))['kpis'])->pluck('key')->all())->toBe(['requests']);
});

it('renders the dashboard with its charts for management', function () {
    $this->actingAs($this->userWithRole(Role::Management))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Income and expenditure')
        ->assertSee('data-chart="cashflow"', false)
        ->assertSee("What's going on", false)
        ->assertSee('vendor/chartjs/chart-4.4.4.umd.min.js', false);
});

it('keeps money off the dashboard for sales', function () {
    $this->actingAs($this->userWithRole(Role::Sales))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Quote requests')
        ->assertDontSee('Income and expenditure')
        ->assertDontSee('Owed to GEA');
});
