<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CareerRole;
use App\Models\Faq;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Website content from the v3 design and GEA's onboarding questionnaire.
 * Safe in production: existing rows (matched by slug or text) are left as staff edited them.
 */
class WebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->services() as $i => $service) {
            Service::query()->firstOrCreate(['slug' => $service['slug']], $service + ['sort' => $i + 1]);
        }

        foreach ($this->faqs() as $i => [$question, $answer]) {
            Faq::query()->firstOrCreate(['question' => $question], ['answer' => $answer, 'sort' => $i + 1]);
        }

        foreach ($this->roles() as $i => [$title, $description]) {
            CareerRole::query()->firstOrCreate(['title' => $title], ['description' => $description, 'sort' => $i + 1]);
        }
    }

    /**
     * @return list<array{slug: string, title: string, short_title: string, summary: string, body: string, highlights: list<string>, media: string}>
     */
    private function services(): array
    {
        return [
            [
                'slug' => 'general-haulage',
                'title' => 'General haulage and trucking',
                'short_title' => 'General haulage',
                'summary' => 'Palletised, bagged and boxed goods moved from factories, depots and markets on a truck sized to your load.',
                'body' => "We move commercial and industrial goods for businesses of every size, from a single 5-tonne load to a full 30-tonne truck.\n\nTell us what you are moving, where it is going and when, and we match the right truck from our fleet of 35.",
                'highlights' => ['Trucks from 5 to 30 tonnes', 'Factory, depot and market pickups', 'One point of contact from booking to delivery'],
                'media' => 'hero-truck',
            ],
            [
                'slug' => 'container-haulage',
                'title' => 'Container haulage',
                'short_title' => 'Container haulage',
                'summary' => '20ft and 40ft containers moved between the ports, inland depots and your warehouse.',
                'body' => "We haul 20ft and 40ft containers from the port to your warehouse or inland depot, and return empties when you are done.\n\nShare your container details and delivery address and we will plan the pickup.",
                'highlights' => ['20ft and 40ft containers', 'Port to warehouse delivery', 'Empty container returns'],
                'media' => 'truck-vehicle',
            ],
            [
                'slug' => 'petroleum-haulage',
                'title' => 'Petroleum products haulage',
                'short_title' => 'Petroleum haulage',
                'summary' => 'Tanker haulage of petroleum products between depots and stations.',
                'body' => "We move petroleum products by tanker between depots, stations and industrial sites, with safety on every trip.\n\nContact us with the product, volume and route for a quote.",
                'highlights' => ['Tanker haulage', 'Depot to station runs', 'Safety-first handling'],
                'media' => 'fleet-truck',
            ],
            [
                'slug' => 'bulk-dry-cargo',
                'title' => 'Bulk and dry cargo',
                'short_title' => 'Bulk and dry cargo',
                'summary' => 'Cement, grain, fertiliser and other bulk loads moved in full truckloads.',
                'body' => "We carry bulk and dry cargo such as cement, grain and fertiliser in full truckloads.\n\nTell us the tonnage and route and we will match a truck to the load.",
                'highlights' => ['Full truckloads', 'Cement, grain, fertiliser and more', 'Careful loading and securing'],
                'media' => 'fleet-boxes',
            ],
            [
                'slug' => 'heavy-equipment',
                'title' => 'Heavy equipment (lowbed and flatbed)',
                'short_title' => 'Heavy equipment',
                'summary' => 'Lowbed and flatbed trucks for machinery, generators and construction plant.',
                'body' => "We move heavy machinery, generators and construction plant on lowbed and flatbed trucks.\n\nSend the equipment type, dimensions and weight so we can plan the right truck and route.",
                'highlights' => ['Lowbed and flatbed trucks', 'Machinery and construction plant', 'Route planning for oversized loads'],
                'media' => 'truck-vehicle',
            ],
            [
                'slug' => 'warehousing',
                'title' => 'Warehousing and storage',
                'short_title' => 'Warehousing',
                'summary' => 'Store goods with us between arrival and delivery.',
                'body' => "We hold your goods between arrival and onward delivery, so you can schedule deliveries when you need them.\n\nAsk us about space, duration and handling for your goods.",
                'highlights' => ['Short and longer-term storage', 'Storage linked to onward delivery'],
                'media' => 'fleet-boxes',
            ],
            [
                'slug' => 'freight-forwarding',
                'title' => 'Freight forwarding and clearing',
                'short_title' => 'Freight forwarding',
                'summary' => 'We handle the paperwork to get cargo cleared, then deliver it on our own trucks.',
                'body' => "We help clear your cargo and then deliver it on our own trucks, so you deal with one company from the port to your door.\n\nShare your shipping documents and delivery address to get started.",
                'highlights' => ['Clearing support', 'Delivery on GEA trucks', 'One company from port to door'],
                'media' => 'people',
            ],
            [
                'slug' => 'interstate-deliveries',
                'title' => 'Interstate deliveries',
                'short_title' => 'Interstate deliveries',
                'summary' => 'Scheduled and one-off runs between Lagos and other states.',
                'body' => "We run deliveries from our Lagos base to other states, for regular schedules or one-off loads.\n\nTell us the pickup town, delivery town and date for a quote.",
                'highlights' => ['Scheduled or one-off runs', 'Departures from Lagos', 'Updates by phone and WhatsApp'],
                'media' => 'hero-truck',
            ],
        ];
    }

    /**
     * Cross-border wording stays out of the FAQ until GEA confirms it (PRD open question 1).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function faqs(): array
    {
        return [
            ['What kind of cargo do you move?', 'General goods, containers, petroleum products, bulk and dry cargo, and heavy equipment on lowbed and flatbed trucks. We also offer warehousing, storage, freight forwarding and clearing.'],
            ['Do you deliver outside Lagos?', 'Yes. We run interstate deliveries from Lagos to other states across Nigeria.'],
            ['What truck sizes are available?', 'Our fleet of 35 trucks covers capacities from 5 tonnes to 30 tonnes, so we can match the truck to your load.'],
            ['How do I get a quote?', 'Fill in the quote form on this page, email sales@geahaulageandlogistics.com, call 0816 216 3235, or message us on WhatsApp with your cargo details and route.'],
            ['How do I track my shipment?', 'Enter the GEA tracking code you received at booking in the tracking bar at the top of this page. For help, email support@geahaulageandlogistics.com.'],
            ['Where is your office?', 'Plot 6, Block 48, Olueasanya Street, off AIT Road, Kola Alagbado, Ifako-Ijaye, Lagos.'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function roles(): array
    {
        return [
            ['Truck drivers', 'Interstate drivers for trucks from 5 to 30 tonnes, including tankers, flatbeds and lowbeds.'],
            ['Drivers’ assistants', 'Support drivers on long routes with loading, securing cargo and paperwork.'],
            ['Fleet mechanics', 'Keep our 35 trucks safe and on the road with preventive maintenance and repairs.'],
            ['Operations and office', 'Dispatch, customer service, billing and logistics coordination at our Lagos office.'],
        ];
    }
}
