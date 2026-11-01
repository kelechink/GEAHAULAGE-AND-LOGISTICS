<?php

declare(strict_types=1);

use App\Models\Service;
use App\Settings\CompanySettings;

it('renders every public page', function (string $path) {
    $this->get($path)->assertOk();
})->with([
    'home' => '/',
    'service' => '/services/container-haulage',
    'quote' => '/quote',
    'careers' => '/careers',
    'track' => '/track',
    'privacy' => '/privacy',
    'terms' => '/terms',
]);

it('shows GEA content from the v3 design on the home page', function () {
    $this->get('/')
        ->assertSee('Moving Goods,')
        ->assertSee('Every delivery is a commitment.')
        ->assertSee('Container haulage')
        ->assertSee('What kind of cargo do you move?')
        ->assertSee('Truck drivers')
        ->assertSee('RC 9198613');
});

it('has a page for each of the 8 services', function () {
    expect(Service::query()->active()->count())->toBe(8);

    Service::query()->active()->get()->each(
        fn (Service $s) => $this->get(route('services.show', $s))->assertOk()->assertSee($s->title)
    );
});

it('returns 404 for unknown or hidden services', function () {
    $this->get('/services/space-travel')->assertNotFound();

    Service::query()->where('slug', 'warehousing')->update(['is_active' => false]);
    $this->get('/services/warehousing')->assertNotFound();
});

it('hides cross-border coverage until GEA confirms it', function () {
    $this->get('/')
        ->assertSee('Across Nigeria.')
        ->assertDontSee('West Africa')
        ->assertSee('coverage-map.html?xb=0', false);

    $settings = app(CompanySettings::class);
    $settings->show_cross_border = true;
    $settings->save();

    $this->get('/')
        ->assertSee('Across Nigeria and West Africa.')
        ->assertSee('coverage-map.html?xb=1', false);
});

it('does not show unproven credentials or placeholder client logos', function () {
    $this->get('/')
        ->assertSee('CAC registration')
        ->assertDontSee('Goods-in-transit insurance')
        ->assertDontSee('Client logo');
});

it('says a tracking code was not found until shipments exist', function () {
    $this->get('/track?code=gea-2611-abcde')
        ->assertOk()
        ->assertSee('GEA-2611-ABCDE')
        ->assertSee("couldn't find", false);
});

it('publishes SEO basics', function () {
    $this->get('/')
        ->assertSee('<meta name="description"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('"@type": "MovingCompany"', false)
        ->assertSee('<link rel="canonical"', false);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('services.show', 'container-haulage'), false);
});
