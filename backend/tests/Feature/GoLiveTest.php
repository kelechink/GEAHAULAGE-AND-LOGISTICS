<?php

declare(strict_types=1);

use App\Domain\Import\DataImporter;
use App\Enums\Role;
use App\Filament\Pages\ImportData;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Truck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

function csvFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'gea').'.csv';
    $f = fopen($path, 'w');
    foreach ($rows as $r) {
        fputcsv($f, $r);
    }
    fclose($f);

    return $path;
}

it('imports trucks then drivers from CSV with friendly column names, and re-imports without duplicates', function () {
    $importer = app(DataImporter::class);
    $trucks = csvFile([
        ['Reg No', 'Truck Type', 'Capacity', 'Make', 'Insurance expiry'],
        ['lag 123 xy', 'Flat bed', '30', 'MAN', '2027-03-31'],
        ['KJA-456-AB', 'Tanker', '33', 'Howo', ''],
    ]);

    $check = $importer->import('trucks', $trucks, false);
    expect($check)->toMatchArray(['rows' => 2, 'created' => 2, 'updated' => 0, 'errors' => [], 'committed' => false])
        ->and(Truck::query()->count())->toBe(0);

    expect($importer->import('trucks', $trucks, true)['committed'])->toBeTrue();
    $t = Truck::query()->where('registration', 'LAG-123-XY')->sole();
    expect($t->type)->toBe('flatbed')->and($t->insurance_expires_on?->toDateString())->toBe('2027-03-31');

    expect($importer->import('trucks', $trucks, true))->toMatchArray(['created' => 0, 'updated' => 2]);
    expect(Truck::query()->count())->toBe(2);

    $drivers = csvFile([
        ['Driver Name', 'Phone Number', 'License', 'Smartphone', 'Usual truck'],
        ['Musa Abdullahi', '0803 111 2222', 'LAG12345AA20', 'yes', 'LAG-123-XY'],
    ]);
    $importer->import('drivers', $drivers, true);
    $d = Driver::query()->sole();
    expect($d->phone)->toBe('2348031112222')->and($d->has_smartphone)->toBeTrue()->and($d->truck_id)->toBe($t->id);
});

it('saves nothing when any row is wrong, and lists every problem by row', function () {
    $file = csvFile([
        ['name', 'phone', 'truck'],
        ['Good Driver', '08031112222', ''],
        ['Bad Phone', '12345', ''],
        ['No Truck', '08039998888', 'ABC-999-ZZ'],
        ['Good Driver again', '0803 111 2222', ''],
    ]);

    $r = app(DataImporter::class)->import('drivers', $file, true);

    expect($r['committed'])->toBeFalse()
        ->and($r['errors'])->toHaveCount(3)
        ->and($r['errors'][0])->toContain('Row 3')->toContain('not a Nigerian phone number')
        ->and($r['errors'][1])->toContain('Row 4')->toContain('Import trucks first')
        ->and($r['errors'][2])->toContain('Row 5')->toContain('same record as row 2')
        ->and(Driver::query()->count())->toBe(0);
});

it('imports customers and routes from Excel', function () {
    $path = tempnam(sys_get_temp_dir(), 'gea').'.xlsx';
    $w = new XlsxWriter;
    $w->openToFile($path);
    $w->addRow(Row::fromValues(['Customer', 'Email', 'Phone', 'Terms']));
    $w->addRow(Row::fromValues(['Obi Foods Ltd', 'ACCOUNTS@obifoods.ng', '0803 123 4567', 30]));
    $w->close();

    app(DataImporter::class)->import('customers', $path, true);
    $c = Customer::query()->where('name', 'Obi Foods Ltd')->sole();
    expect($c->email)->toBe('accounts@obifoods.ng')->and($c->phone)->toBe('2348031234567');

    $places = csvFile([['Town', 'State', 'Latitude', 'Longitude'], ['Okene', 'Kogi', '7.55', '6.23'], ['London', 'UK', '51.5', '-0.12']]);
    $r = app(DataImporter::class)->import('places', $places, true);
    expect($r['errors'])->toHaveCount(1)->and($r['errors'][0])->toContain('inside Nigeria');
});

it('lets an admin check and import a file from the admin page', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    $file = UploadedFile::fake()->createWithContent('trucks.csv', "registration,type,capacity_tonnes\nABJ-777-KK,tipper,20\n");

    Livewire::actingAs($admin)->test(ImportData::class)
        ->set('data.type', 'trucks')
        ->set('data.file', [$file])
        ->call('check')
        ->assertSet('report.created', 1)
        ->assertSet('report.committed', false)
        ->call('save')
        ->assertSet('report.committed', true);

    expect(Truck::query()->where('registration', 'ABJ-777-KK')->exists())->toBeTrue();
    $this->actingAs($this->userWithRole(Role::Sales))->get('/admin/import')->assertForbidden();
});

it('reports health for uptime monitoring', function () {
    $this->getJson('/health')->assertStatus(503)->assertJsonPath('checks.database', 'ok')->assertJsonPath('checks.scheduler', 'stale');

    Cache::forever('schedule:heartbeat', now()->toIso8601String());
    $this->getJson('/health')->assertOk()->assertJsonPath('status', 'ok');
});

it('keeps backups for 30 days and schedules them nightly', function () {
    expect(config('backup.cleanup.default_strategy.keep_all_backups_for_days'))->toBe(30)
        ->and(config('backup.backup.source.databases'))->not->toBeEmpty();

    $this->artisan('schedule:list')->expectsOutputToContain('backup:run')->expectsOutputToContain('backup:clean');
});
