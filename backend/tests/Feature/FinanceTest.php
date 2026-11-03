<?php

declare(strict_types=1);

use App\Domain\Crm\LeadCaptureService;
use App\Domain\Finance\AmountInWords;
use App\Domain\Finance\Documents\DocumentData;
use App\Domain\Finance\Documents\DocumentRenderer;
use App\Domain\Finance\DocumentTotals;
use App\Domain\Finance\FinanceException;
use App\Domain\Finance\FinanceReports;
use App\Domain\Finance\InvoiceService;
use App\Domain\Finance\NumberSequence;
use App\Domain\Finance\PaymentService;
use App\Domain\Finance\QuoteService;
use App\Domain\Shipping\ShipmentService;
use App\Enums\ExpenseCategory;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Enums\LeadStage;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\MaintenanceRecord;
use App\Models\Truck;
use App\Settings\WebsiteSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    Http::preventStrayRequests();
});

/** PRD 19 "Basic" invoice: 2 × ₦150,000 + 1 × ₦50,000, discount ₦10,000, VAT 7.5%. */
function basicInvoice(array $attrs = []): Invoice
{
    return app(InvoiceService::class)->saveDraft(null, array_merge([
        'customer_id' => Customer::factory()->create(['email' => 'accounts@obifoods.ng', 'payment_terms_days' => 30])->id,
        'discount_kobo' => 1_000_000,
        'vat_rate_bp' => 750,
    ], $attrs), [
        ['description' => 'Haulage Apapa to Kano', 'quantity' => '2', 'unit_price_kobo' => 15_000_000],
        ['description' => 'Offloading', 'quantity' => '1', 'unit_price_kobo' => 5_000_000],
    ]);
}

// ---- PRD 19 required cases --------------------------------------------------

it('PRD 19 Basic: subtotal 350,000; taxable 340,000; VAT 25,500; total 365,500', function () {
    $i = basicInvoice();

    expect($i->subtotal_kobo)->toBe(35_000_000)
        ->and($i->subtotal_kobo - $i->discount_kobo)->toBe(34_000_000)
        ->and($i->vat_kobo)->toBe(2_550_000)
        ->and($i->total_kobo)->toBe(36_550_000);
});

it('PRD 19 Paid + WHT: total 365,500; paid 100,000; WHT 17,000 → balance 248,500', function () {
    $i = basicInvoice();
    app(InvoiceService::class)->issue($i);

    app(PaymentService::class)->record($i->customer, 10_000_000, PaymentMethod::BankTransfer, now(), 'TRF1', 1_700_000,
        [['invoice_id' => $i->id, 'amount_kobo' => 10_000_000, 'wht_kobo' => 1_700_000]]);

    expect($i->refresh()->balance_kobo)->toBe(24_850_000)
        ->and($i->status)->toBe(InvoiceStatus::PartiallyPaid);
});

it('PRD 19 Exempt: VAT 0 means total equals taxable', function () {
    $i = basicInvoice(['vat_rate_bp' => 0]);

    expect($i->vat_kobo)->toBe(0)->and($i->total_kobo)->toBe(34_000_000);
});

it('PRD 19 Rounding: taxable ₦1,000.33 at 7.5% → VAT ₦75.02', function () {
    $t = DocumentTotals::calculate([['quantity' => '1', 'unit_price_kobo' => 100_033]], 0, 750);

    expect($t->vat)->toBe(7_502);
});

it('PRD 19 Void: a paid invoice cannot be voided until a credit note exists', function () {
    $i = basicInvoice();
    $svc = app(InvoiceService::class);
    $svc->issue($i);
    app(PaymentService::class)->record($i->customer, $i->refresh()->balance_kobo, PaymentMethod::BankTransfer, now());
    expect($i->refresh()->status)->toBe(InvoiceStatus::Paid);

    expect(fn () => $svc->void($i, 'Wrong customer'))->toThrow(FinanceException::class, 'Issue a credit note');

    $svc->creditNote($i, $i->total_kobo, 'Refunded in full');
    $svc->void($i->refresh(), 'Wrong customer');

    expect($i->refresh()->status)->toBe(InvoiceStatus::Void)->and(Invoice::query()->whereKey($i->id)->exists())->toBeTrue();
});

it('PRD 19 Numbering: invoices issued together get unique, sequential numbers with no gaps', function () {
    $svc = app(InvoiceService::class);
    $invoices = [basicInvoice(), basicInvoice(), basicInvoice()];
    foreach ($invoices as $i) {
        $svc->issue($i);
    }
    $year = now()->timezone('Africa/Lagos')->format('Y');

    expect(collect($invoices)->map(fn ($i) => $i->refresh()->number)->all())
        ->toBe(["GEA-INV-{$year}-00001", "GEA-INV-{$year}-00002", "GEA-INV-{$year}-00003"]);

    // Drafts never take a number, so a draft that is abandoned leaves no gap.
    basicInvoice();
    $svc->issue(basicInvoice());
    expect(Invoice::query()->whereNotNull('number')->orderBy('number')->pluck('number')->last())->toBe("GEA-INV-{$year}-00004");
});

it('PRD 19 Word export: the Basic invoice opens as .docx with totals identical to the PDF', function () {
    $i = basicInvoice();
    app(InvoiceService::class)->issue($i);
    $r = app(DocumentRenderer::class);

    $docx = $r->docx($i->refresh());
    $pdf = $r->pdf($i);

    $tmp = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($tmp, $docx);
    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue();
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    $data = app(DocumentData::class)->for($i);
    expect($data['total'])->toBe('₦365,500.00')
        ->and($xml)->toContain('₦365,500.00')->toContain('₦25,500.00')->toContain('₦350,000.00')
        ->and(substr($pdf, 0, 4))->toBe('%PDF');
});

// ---- Other finance rules ----------------------------------------------------

it('writes amounts in words', function () {
    expect(AmountInWords::naira(36_550_000))->toBe('Three hundred and sixty-five thousand, five hundred naira only')
        ->and(AmountInWords::naira(100_502))->toBe('One thousand and five naira, two kobo only')
        ->and(AmountInWords::naira(250_000_000_00))->toBe('Two hundred and fifty million naira only');
});

it('only edits drafts and needs lines before issuing', function () {
    $svc = app(InvoiceService::class);
    $empty = $svc->saveDraft(null, ['customer_id' => Customer::factory()->create()->id], []);
    expect(fn () => $svc->issue($empty))->toThrow(FinanceException::class, 'Add at least one line');

    $i = basicInvoice();
    $svc->issue($i);
    expect(fn () => $svc->saveDraft($i, [], []))->toThrow(FinanceException::class, 'Only draft invoices');
});

it('sets the due date from the customer payment terms', function () {
    $i = basicInvoice();
    app(InvoiceService::class)->issue($i);

    expect($i->refresh()->due_date->toDateString())->toBe(now()->timezone('Africa/Lagos')->startOfDay()->addDays(30)->toDateString());
});

it('needs management to issue an invoice with a discount over 10%', function () {
    $i = basicInvoice(['discount_kobo' => 5_000_000]); // ~14% of 350,000

    expect(fn () => app(InvoiceService::class)->issue($i, $this->userWithRole(Role::Finance)))->toThrow(FinanceException::class, 'need Management');
    app(InvoiceService::class)->issue($i, $this->userWithRole(Role::Management));
    expect($i->refresh()->status)->toBe(InvoiceStatus::Issued);
});

it('applies payments oldest invoice first and keeps any extra as credit', function () {
    $customer = Customer::factory()->create();
    $svc = app(InvoiceService::class);
    $a = basicInvoice(['customer_id' => $customer->id]);
    $svc->issue($a);
    $this->travel(1)->days();
    $b = basicInvoice(['customer_id' => $customer->id]);
    $svc->issue($b);

    $payment = app(PaymentService::class)->record($customer, 36_550_000 + 10_000_000 + 500_000, PaymentMethod::Cash, now());

    expect($a->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($b->refresh()->balance_kobo)->toBe(36_550_000 - 10_500_000)
        ->and($payment->refresh()->unallocated_kobo)->toBe(0);

    $extra = app(PaymentService::class)->record($customer, 40_000_000, PaymentMethod::Cash, now());
    expect($b->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($extra->refresh()->unallocated_kobo)->toBe(40_000_000 - 26_050_000)
        ->and(app(PaymentService::class)->creditBalance($customer))->toBe(13_950_000);
});

it('refuses to over-allocate a payment to one invoice', function () {
    $i = basicInvoice();
    app(InvoiceService::class)->issue($i);

    expect(fn () => app(PaymentService::class)->record($i->customer, 50_000_000, PaymentMethod::Cash, now(), null, 0,
        [['invoice_id' => $i->id, 'amount_kobo' => 50_000_000]]))->toThrow(FinanceException::class, 'only has');
});

it('marks unpaid invoices overdue and sends reminders on schedule', function () {
    $i = basicInvoice();
    app(InvoiceService::class)->issue($i);
    $due = $i->refresh()->due_date;

    $this->travelTo($due->copy()->subDays(3)->setTime(9, 0));
    $this->artisan('finance:daily');
    Mail::assertQueued(InvoiceMail::class, fn ($m) => $m->kind === 'due_soon');

    $this->travelTo($due->copy()->addDays(2)->setTime(9, 0));
    $this->artisan('finance:daily');
    expect($i->refresh()->status)->toBe(InvoiceStatus::Overdue);
    Mail::assertQueued(InvoiceMail::class, 1); // overdue, but not yet a week

    $this->travelTo($due->copy()->addDays(7)->setTime(9, 0));
    $this->artisan('finance:daily');
    Mail::assertQueued(InvoiceMail::class, fn ($m) => $m->kind === 'overdue');
    Mail::assertQueued(InvoiceMail::class, 2);
});

it('creates a draft invoice when a shipment is delivered', function () {
    $ops = $this->userWithRole(Role::Operations);
    $svc = app(ShipmentService::class);
    $s = $svc->create(['customer_id' => Customer::factory()->create()->id, 'cargo_description' => 'Rice', 'tonnage' => 10, 'origin' => 'Apapa', 'destination' => 'Ibadan']);
    $svc->assign($s, Truck::factory()->create(['capacity_tonnes' => 15]), null, $ops);
    foreach ([ShipmentStage::Loading, ShipmentStage::Loaded, ShipmentStage::InTransit, ShipmentStage::Arrived] as $st) {
        $svc->transition($s, $st, TrackingSource::Manual, $ops);
    }
    $svc->transition($s, ShipmentStage::Delivered, TrackingSource::Manual, $ops, null, [], true, ['receiver_name' => 'Store manager']);

    $draft = Invoice::query()->where('shipment_id', $s->id)->sole();
    expect($draft->status)->toBe(InvoiceStatus::Draft)
        ->and($draft->template)->toBe(InvoiceTemplate::HaulageTrip)
        ->and($draft->lines()->first()?->description)->toContain($s->tracking_code);
});

it('turns a sent quote into the lead\'s "Quoted" stage, then into an invoice', function () {
    $lead = app(LeadCaptureService::class)->captureQuoteRequest(['name' => 'Ada', 'phone' => '08030000001', 'company' => 'Obi Foods Ltd', 'from' => 'Apapa', 'to' => 'Kano']);
    $customer = Customer::factory()->create();
    $qs = app(QuoteService::class);
    $quote = $qs->save(null, ['customer_id' => $customer->id, 'lead_id' => $lead->id], [['description' => 'Haulage', 'quantity' => '2', 'unit_price_kobo' => 95_000_000]]);

    expect($quote->number)->toStartWith('GEA-PRO-');
    $qs->markSent($quote);
    expect($lead->refresh()->stage)->toBe(LeadStage::Quoted)->and($lead->estimated_value_kobo)->toBe($quote->total_kobo);

    $invoice = $qs->convertToInvoice($quote->refresh());
    expect($invoice->total_kobo)->toBe($quote->total_kobo)->and($invoice->quote_id)->toBe($quote->id);
});

it('submits e-invoices through the provider and flags missing IRNs', function () {
    $i = basicInvoice(['template' => InvoiceTemplate::EInvoice]);
    app(InvoiceService::class)->issue($i);

    expect($i->refresh()->irn)->toStartWith('MOCK-')->and($i->qr_payload)->not->toBeNull();
    $data = app(DocumentData::class)->for($i);
    expect($data['einvoice']['qr'])->toStartWith('data:image/png;base64,');

    $plain = basicInvoice();
    app(InvoiceService::class)->issue($plain);
    expect($plain->refresh()->needsEInvoice(true))->toBeTrue()->and($plain->needsEInvoice(false))->toBeFalse();
});

it('renders all five templates as PDF', function (InvoiceTemplate $template) {
    $i = basicInvoice(['template' => $template === InvoiceTemplate::Proforma ? InvoiceTemplate::CorporateClassic : $template]);
    expect(substr(app(DocumentRenderer::class)->pdf($i), 0, 4))->toBe('%PDF');
})->with(InvoiceTemplate::cases());

it('posts maintenance costs to expenses and keeps them in step', function () {
    $r = MaintenanceRecord::query()->create(['truck_id' => Truck::factory()->create()->id, 'done_on' => now(), 'description' => 'Brakes', 'cost_kobo' => 3_800_000]);
    $r->update(['cost_kobo' => 4_000_000]);

    expect(Expense::query()->where('maintenance_record_id', $r->id)->sole())
        ->amount_kobo->toBe(4_000_000)
        ->category->toBe(ExpenseCategory::Maintenance);
});

it('reports ageing, profit and monthly cash flow', function () {
    $truck = Truck::factory()->create();
    $i = basicInvoice();
    app(InvoiceService::class)->issue($i);
    Expense::query()->create(['category' => 'fuel', 'spent_on' => now(), 'amount_kobo' => 2_000_000, 'description' => 'Diesel', 'truck_id' => $truck->id]);
    app(PaymentService::class)->record($i->customer, 10_000_000, PaymentMethod::BankTransfer, now());

    $this->travel(45)->days();
    $r = app(FinanceReports::class);
    $ageing = $r->ageing();
    expect($ageing['buckets']['0–30'])->toBe(26_550_000);

    $flow = $r->monthlyCashflow(3);
    expect(collect($flow)->sum('income'))->toBe(10_000_000)->and(collect($flow)->sum('expenses'))->toBe(2_000_000);
});

it('gapless sequences start again each year', function () {
    $seq = app(NumberSequence::class);
    expect($seq->next('INV', 2026))->toBe('GEA-INV-2026-00001')
        ->and($seq->next('INV', 2026))->toBe('GEA-INV-2026-00002')
        ->and($seq->next('INV', 2027))->toBe('GEA-INV-2027-00001');
});

it('keeps finance screens to finance roles', function (string $path) {
    $this->actingAs($this->userWithRole(Role::Finance))->get($path)->assertOk();
    $this->actingAs($this->userWithRole(Role::Operations))->get($path)->assertForbidden();
})->with(['/admin/invoices', '/admin/invoices/create', '/admin/payments', '/admin/expenses', '/admin/finance-reports']);

it('lets sales prepare quotes but not invoices', function () {
    $this->actingAs($this->userWithRole(Role::Sales));
    $this->get('/admin/quotes/create')->assertOk();
    $this->get('/admin/invoices')->assertForbidden();
});

it('lets management void but finance staff cannot', function () {
    $i = basicInvoice();
    app(InvoiceService::class)->issue($i);

    $this->actingAs($this->userWithRole(Role::Finance));
    Livewire\Livewire::test(ViewInvoice::class, ['record' => $i->id])->assertActionHidden('void');
    $this->actingAs($this->userWithRole(Role::Management));
    Livewire\Livewire::test(ViewInvoice::class, ['record' => $i->id])
        ->callAction('void', ['reason' => 'Duplicate'])->assertHasNoActionErrors();
    expect($i->refresh()->status)->toBe(InvoiceStatus::Void);
});

it('creates an invoice from the admin form with live-calculated totals', function () {
    $this->actingAs($this->userWithRole(Role::Finance));
    $customer = Customer::factory()->create();

    Livewire\Livewire::test(CreateInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'template' => 'corporate_classic',
            'vat_rate' => '7.5',
            'discount_kobo' => '10,000',
            'lines' => [
                ['description' => 'Haulage', 'quantity' => '2', 'unit_price_kobo' => '150,000'],
                ['description' => 'Offloading', 'quantity' => '1', 'unit_price_kobo' => '50000'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Invoice::query()->sole()->total_kobo)->toBe(36_550_000);
});

it('sends invoices from the verified sender with replies to the billing mailbox', function () {
    config(['mail.from.address' => 'info@geahaulageandlogistics.com', 'mail.billing_from' => null]);
    $mail = new InvoiceMail(basicInvoice());

    expect($mail->envelope()->from?->address)->toBe('info@geahaulageandlogistics.com')
        ->and($mail->envelope()->replyTo[0]->address)->toBe(app(WebsiteSettings::class)->email_billing);

    config(['mail.billing_from' => 'billing@geahaulageandlogistics.com']);
    expect((new InvoiceMail(basicInvoice()))->envelope()->from?->address)->toBe('billing@geahaulageandlogistics.com');
});
