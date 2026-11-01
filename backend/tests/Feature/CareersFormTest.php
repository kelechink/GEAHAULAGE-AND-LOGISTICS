<?php

declare(strict_types=1);

use App\Models\JobApplication;

it('stores a job application', function () {
    $this->from('/careers')->post('/careers', [
        'name' => 'Musa Bello',
        'phone' => '08051234567',
        'role' => 'Truck drivers',
        'years' => 8,
        'licence' => 'Class E',
        'about' => 'Lagos to Kano route, 30 tonne trucks.',
    ])->assertRedirect('/careers#careers')->assertSessionHas('applied', 'Musa');

    expect(JobApplication::query()->sole())
        ->name->toBe('Musa Bello')
        ->years_experience->toBe(8)
        ->licence_class->toBe('Class E');
});

it('requires a name, phone and role', function () {
    $this->post('/careers', ['name' => '', 'phone' => '', 'role' => ''])
        ->assertSessionHasErrors(['name', 'phone', 'role']);

    expect(JobApplication::query()->count())->toBe(0);
});

it('rejects the honeypot', function () {
    $this->post('/careers', ['name' => 'Bot', 'phone' => '08051234567', 'role' => 'Truck drivers', 'website' => 'x'])
        ->assertSessionHasErrors('website');

    expect(JobApplication::query()->count())->toBe(0);
});
