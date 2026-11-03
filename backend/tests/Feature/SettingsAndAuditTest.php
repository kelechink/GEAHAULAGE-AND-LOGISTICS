<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Pages\ManageCompanySettings;
use App\Settings\CompanySettings;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

it('seeds GEA company details with cross-border coverage hidden', function () {
    $settings = app(CompanySettings::class);

    expect($settings->company_name)->toBe('GEA Haulage and Logistics')
        ->and($settings->rc_number)->toBe('9198613')
        ->and($settings->vat_rate)->toBe(7.5)
        ->and($settings->show_cross_border)->toBeFalse();
});

it('saves company settings from the admin page', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin));

    Livewire::test(ManageCompanySettings::class)
        ->fillForm(['tin' => '12345678-0001', 'show_cross_border' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(CompanySettings::class);
    $settings->refresh();

    expect($settings->tin)->toBe('12345678-0001')
        ->and($settings->show_cross_border)->toBeTrue();
});

it('rejects a WhatsApp number that is not in 234 format', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin));

    Livewire::test(ManageCompanySettings::class)
        ->fillForm(['whatsapp' => '08162163235'])
        ->call('save')
        ->assertHasFormErrors(['whatsapp']);
});

it('writes changes to users into the audit log with who made them', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $staff = $this->userWithRole(Role::Sales, ['name' => 'Old Name']);
    $this->actingAs($admin);

    $staff->update(['name' => 'New Name']);

    $entry = Activity::query()->where('subject_id', $staff->id)->where('event', 'updated')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe($admin->id)
        ->and($entry->properties['old']['name'])->toBe('Old Name')
        ->and($entry->properties['attributes']['name'])->toBe('New Name');
});
