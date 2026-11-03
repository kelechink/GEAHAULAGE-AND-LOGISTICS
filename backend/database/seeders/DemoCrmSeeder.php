<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crm\LeadCaptureService;
use App\Domain\Crm\LeadPipeline;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Crm\Lead;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

/**
 * Demo CRM data for local and staging (PRD 18.2): 15 customers and 30 leads on Nigerian routes.
 */
class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        if (Lead::query()->exists()) {
            return;
        }

        Mail::fake();
        $capture = app(LeadCaptureService::class);
        $pipeline = app(LeadPipeline::class);

        $companies = ['Dangote Cement Depot (demo)', 'Obi Foods Ltd', 'Lekki Steel Works', 'Kano Grains Co', 'Sapele Timber', 'Ikeja Beverages',
            'Onitsha Traders Union', 'Abuja BuildMart', 'Port Harcourt Marine Supplies', 'Ibadan Agro Mills', 'Enugu Paints', 'Jos Tin Supplies',
            'Kaduna Textiles', 'Benin Rubber Co', 'Aba Shoemakers Coop'];
        $routes = [['Apapa, Lagos', 'Kano'], ['Ikeja, Lagos', 'Abuja'], ['Tin Can, Lagos', 'Ibadan'], ['Agbara, Lagos', 'Port Harcourt'],
            ['Lekki, Lagos', 'Benin City'], ['Apapa, Lagos', 'Onitsha'], ['Ota, Ogun', 'Kaduna'], ['Ikorodu, Lagos', 'Enugu'], ['Apapa, Lagos', 'Jos'], ['Badagry, Lagos', 'Sokoto']];
        $services = ['General haulage and trucking', 'Container haulage', 'Bulk and dry cargo', 'Heavy equipment (lowbed and flatbed)', 'Interstate deliveries'];
        $names = ['Ada Obi', 'Musa Bello', 'Chinedu Okafor', 'Funke Adeyemi', 'Ibrahim Sani', 'Ngozi Eze', 'Tunde Bakare', 'Aisha Lawal', 'Emeka Nwosu', 'Kemi Ojo'];
        $stages = [LeadStage::New, LeadStage::New, LeadStage::Contacted, LeadStage::Qualified, LeadStage::Quoted, LeadStage::Won, LeadStage::Lost];

        for ($i = 0; $i < 30; $i++) {
            [$from, $to] = $routes[$i % count($routes)];
            $lead = $capture->captureQuoteRequest([
                'name' => $names[$i % count($names)],
                'phone' => '080'.str_pad((string) (31000000 + $i * 7919), 8, '0', STR_PAD_LEFT),
                'company' => $companies[$i % count($companies)],
                'service' => $services[$i % count($services)],
                'from' => $from,
                'to' => $to,
                'details' => (5 + ($i % 6) * 5).' tonnes',
            ], $i % 4 === 0 ? LeadSource::Phone : LeadSource::WebsiteForm);

            $lead->update([
                'estimated_value_kobo' => (250_000 + $i * 37_500) * 100,
                'tonnage' => 5 + ($i % 6) * 5,
                'created_at' => now()->subDays(60 - $i * 2),
            ]);

            $stage = $stages[$i % count($stages)];
            if ($stage !== LeadStage::New) {
                $pipeline->move($lead, $stage, null, $stage === LeadStage::Lost ? 'Price too high' : null);
            }
        }

        // Make sure there are 15 customers in total.
        $missing = 15 - Customer::query()->count();
        if ($missing > 0) {
            Customer::factory()->count($missing)->create();
        }
    }
}
