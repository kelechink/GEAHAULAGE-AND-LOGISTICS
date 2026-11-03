<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Pages\ManageWebsiteContent;
use App\Settings\WebsiteSettings;
use Livewire\Livewire;

it('lets the super admin edit website copy and shows it on the site', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin));

    $component = Livewire::test(ManageWebsiteContent::class);
    $component->fillForm(['about_heading' => 'Every load is a promise.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(tap(app(WebsiteSettings::class))->refresh()->about_heading)->toBe('Every load is a promise.');
    $this->get('/')->assertSee('Every load is a promise.');
});

it('keeps lists as plain strings after saving', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin));

    Livewire::test(ManageWebsiteContent::class)->call('save')->assertHasNoFormErrors();

    $site = tap(app(WebsiteSettings::class))->refresh();
    expect($site->hero_lines)->toBe(['Moving Goods,', 'Building Trust,', 'Delivering Excellence.'])
        ->and($site->values[0])->toBe(['title' => 'Integrity', 'text' => 'We conduct our business with honesty, transparency, and accountability.']);
});

it('shows content screens only to users with content.manage', function (string $path) {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get($path)->assertOk();
    $this->actingAs($this->userWithRole(Role::Operations))->get($path)->assertForbidden();
})->with(['/admin/services', '/admin/faqs', '/admin/career-roles', '/admin/manage-website-content']);

it('shows website leads to sales and job applications to operations', function () {
    $this->actingAs($this->userWithRole(Role::Sales))->get('/admin/leads')->assertOk();
    $this->actingAs($this->userWithRole(Role::Finance))->get('/admin/leads')->assertForbidden();

    $this->actingAs($this->userWithRole(Role::Operations))->get('/admin/job-applications')->assertOk();
    $this->actingAs($this->userWithRole(Role::Sales))->get('/admin/job-applications')->assertForbidden();
});
