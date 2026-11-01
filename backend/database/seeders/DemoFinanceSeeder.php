<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Finance\InvoiceService;
use App\Domain\Finance\PaymentService;
use App\Domain\Finance\QuoteService;
use App\Enums\ExpenseCategory;
use App\Enums\InvoiceTemplate;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\MaintenanceRecord;
use App\Models\Shipment;
use App\Models\Truck;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo finance data across the last 12 months (PRD 18.2: 25 invoices) so reports and the dashboard have real figures.
 */
class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        if (Invoice::query()->exists()) {
            return;
        }

        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        $customers = Customer::query()->get();
        $trips = Shipment::query()->whereIn('stage', ['delivered', 'closed'])->get();
        $trucks = Truck::query()->pluck('id')->all();
        $templates = [InvoiceTemplate::HaulageTrip, InvoiceTemplate::HaulageTrip, InvoiceTemplate::CorporateClassic, InvoiceTemplate::BoldNavy, InvoiceTemplate::EInvoice];
        $realNow = Carbon::now();

        // Seasonal shape: busier towards the end of the year.
        $perMonth = [1, 1, 2, 2, 2, 2, 2, 2, 3, 3, 3, 2];
        $n = 0;
        foreach ($perMonth as $m => $count) {
            for ($k = 0; $k < $count; $k++, $n++) {
                $issueAt = $realNow->copy()->startOfMonth()->subMonths(11 - $m)->addDays(2 + $k * 9)->setTime(10, 0);
                if ($issueAt->gt($realNow)) {
                    $issueAt = $realNow->copy()->subDay();
                }
                Carbon::setTestNow($issueAt);

                $customer = $customers[$n % $customers->count()];
                $trip = $trips[$n % max(1, $trips->count())] ?? null;
                $rate = [950_000, 1_200_000, 1_450_000, 1_800_000, 2_200_000, 2_600_000][$n % 6] * 100; // per truck, per trip
                $inv = $invoices->saveDraft(null, [
                    'customer_id' => $customer->id,
                    'shipment_id' => $n < $trips->count() ? $trip?->id : null,
                    'template' => $templates[$n % count($templates)],
                    'discount_kobo' => $n % 7 === 0 ? 1_000_000 : 0,
                ], [
                    ['description' => 'Haulage: '.($trip !== null ? "{$trip->origin} to {$trip->destination}" : 'Lagos to Abuja'), 'quantity' => (string) (2 + $n % 5), 'unit_price_kobo' => $rate],
                    ['description' => 'Loading and offloading', 'quantity' => '1', 'unit_price_kobo' => 2_500_000],
                ]);
                $invoices->issue($inv);

                // Most older invoices are paid; recent ones are open or part paid; a few go overdue.
                $age = 11 - $m;
                Carbon::setTestNow($issueAt->copy()->addDays(12 + ($n % 10)));
                if ($age >= 2 && $n % 9 !== 4) {
                    $wht = $n % 3 === 0 ? (int) round($inv->total_kobo * 5 / 100) : 0;
                    $payments->record($customer, $inv->refresh()->balance_kobo - $wht, PaymentMethod::BankTransfer, Carbon::now(), 'TRF'.(880000 + $n), $wht,
                        [['invoice_id' => $inv->id, 'amount_kobo' => $inv->balance_kobo - $wht, 'wht_kobo' => $wht]]);
                } elseif ($age === 1 && $n % 2 === 0) {
                    $payments->record($customer, intdiv($inv->refresh()->balance_kobo, 2), PaymentMethod::BankTransfer, Carbon::now(), 'TRF'.(880000 + $n), 0,
                        [['invoice_id' => $inv->id, 'amount_kobo' => intdiv($inv->balance_kobo, 2)]]);
                }
            }
        }

        Carbon::setTestNow($realNow);
        $invoices->markOverdue();

        // Two quotes waiting on customers, and one delivered trip left as a draft for Finance.
        $quotes = app(QuoteService::class);
        foreach ([0, 1] as $i) {
            $quotes->save(null, ['customer_id' => $customers[$i]->id], [
                ['description' => 'Haulage: Apapa to Kano, 30 t', 'quantity' => '2', 'unit_price_kobo' => 95_000_000],
            ]);
        }
        if ($trips->isNotEmpty()) {
            $invoices->draftFromShipment($trips->last());
        }

        // Expenses: monthly fixed costs plus trip costs, with a steady upward drift in fuel.
        for ($m = 0; $m < 12; $m++) {
            $month = $realNow->copy()->startOfMonth()->subMonths(11 - $m);
            $rows = [
                [ExpenseCategory::Salaries, 5, 280_000_000, 'Staff salaries', null],
                [ExpenseCategory::Rent, 1, 45_000_000, 'Office and yard rent', null],
                [ExpenseCategory::Fuel, 8, (150_000_000 + $m * 6_000_000), 'Diesel for interstate trips', $trucks[$m % count($trucks)] ?? null],
                [ExpenseCategory::DriverAllowances, 10, 60_000_000 + ($m % 3) * 5_000_000, 'Driver trip allowances', null],
                [ExpenseCategory::Tolls, 15, 12_000_000 + $m * 400_000, 'Tolls and state levies', null],
            ];
            if ($m % 4 === 2) {
                $rows[] = [ExpenseCategory::Insurance, 20, 75_000_000, 'Goods-in-transit insurance premium', null];
            }
            foreach ($rows as [$cat, $day, $amount, $desc, $truck]) {
                $date = $month->copy()->addDays($day - 1);
                if ($date->gt($realNow)) {
                    continue;
                }
                Expense::query()->create(['category' => $cat, 'spent_on' => $date, 'amount_kobo' => $amount, 'description' => $desc, 'truck_id' => $truck]);
            }
        }

        // A few maintenance jobs; each posts to Expenses automatically.
        foreach (array_slice($trucks, 0, 6) as $i => $truckId) {
            MaintenanceRecord::query()->create([
                'truck_id' => $truckId,
                'done_on' => $realNow->copy()->subDays(20 + $i * 41),
                'description' => ['Brake pads and service', 'Tyre replacement (4)', 'Gearbox repair', 'Engine service', 'Clutch replacement', 'Electrical fault'][$i],
                'cost_kobo' => [38_000_000, 96_000_000, 210_000_000, 45_000_000, 130_000_000, 22_000_000][$i],
                'workshop' => 'Alagbado Motors',
            ]);
        }
    }
}
