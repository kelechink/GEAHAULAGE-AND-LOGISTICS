<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ai\KnowledgeItem;
use App\Models\CareerRole;
use App\Models\Faq;
use App\Models\Service;
use App\Settings\CompanySettings;
use App\Settings\WebsiteSettings;
use Illuminate\Database\Seeder;

/**
 * Seeds the assistant's knowledge from the website copy (PRD 12.2). Production-safe: items are matched by
 * title and only created if missing, so edits made in admin are kept.
 */
class KnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $w = app(WebsiteSettings::class);
        $c = app(CompanySettings::class);
        $sort = 0;
        $add = function (string $category, string $title, string $body, string $keywords = '') use (&$sort): void {
            KnowledgeItem::query()->firstOrCreate(['title' => $title], ['category' => $category, 'body' => trim($body), 'keywords' => $keywords, 'sort' => $sort++]);
        };

        // Office hours are not confirmed yet: add them to this item in Assistant → Knowledge.
        $add('contact', 'Contact details and office hours', "Phone: {$c->phone}. WhatsApp: {$c->whatsapp}. Email: {$w->email_info} (sales: {$w->email_sales}). "
            ."Office: {$c->address}. Quote requests get a reply within one working day.",
            'phone, call, whatsapp, email, address, office, hours, location, reach');

        $add('company', 'About GEA', "{$c->company_name} is a Nigerian haulage and logistics company based in Lagos, moving goods across Nigeria since 2020. Mission: {$w->mission} Vision: {$w->vision}",
            'about, company, who, history, mission, vision, since');
        $add('company', 'Our values', collect($w->values)->map(fn (array $v): string => "{$v['title']}: {$v['text']}")->implode(' '), 'values, integrity, safety, reliability');
        $add('company', 'Why choose GEA', implode(' ', $w->reasons).' '.$w->reasons_closing, 'why, choose, reliable, trust, better');

        $add('fleet', 'Fleet and capacity', 'GEA runs a fleet of 35 trucks from 5 to 30 tonnes for general cargo, containers, petroleum products, bulk cargo and heavy equipment. '
            .'We match the truck to the load. Every trip gets a GEA tracking code.', 'fleet, truck, trucks, tonnes, tonne, capacity, size, trailer, tanker, flatbed, tipper');
        $add('coverage', 'Where we deliver', 'We move goods across Nigeria from our base in Lagos. For any specific route, ask for a quote and the team will confirm.', 'coverage, route, routes, deliver, states, cities, nationwide, abuja, kano, port harcourt');
        $add('process', 'How booking works', collect($w->steps)->map(fn (array $s, int $i): string => ($i + 1).". {$s['title']}: {$s['text']}")->implode(' '),
            'how, book, booking, process, steps, start, order');
        $add('process', 'Prices and quotes', 'Prices depend on the route, cargo, tonnage and date, so every job gets its own quote. The assistant cannot give prices. '
            .'Share the pickup and delivery towns, cargo, tonnage and date to get a quote within one working day.', 'price, cost, rate, quote, how much, charge, fee');
        $add('process', 'Tracking a shipment', 'Every trip has a GEA tracking code like GEA-2611-AB3CD, on the waybill and in the booking message. Enter it on the Track page to see the stage, '
            .'the last known town and the ETA window. Lost your code? Call or WhatsApp us with your name and route.', 'track, tracking, code, where, status, eta, shipment');

        foreach (Service::query()->where('is_active', true)->orderBy('sort')->get() as $s) {
            $add('services', $s->title, $s->summary.' '.strip_tags((string) $s->body), strtolower($s->title.', '.$s->short_title.', '.$s->slug));
        }
        foreach (Faq::query()->where('is_active', true)->orderBy('sort')->get() as $f) {
            $add('faq', $f->question, $f->answer);
        }
        $open = CareerRole::query()->where('is_open', true)->pluck('title')->implode(', ');
        $add('careers', 'Careers at GEA', strip_tags($w->careers_intro).($open !== '' ? " Open roles: {$open}." : '').' Apply on the Careers page.', 'job, jobs, career, careers, hiring, vacancy, apply, driver job');
    }
}
