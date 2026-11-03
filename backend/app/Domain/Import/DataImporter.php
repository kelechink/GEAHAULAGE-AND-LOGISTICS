<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Crm\PhoneNumber;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Place;
use App\Models\Truck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * CSV / Excel import for go-live (PRD 17, Phase 7): customers, trucks, drivers and routes/checkpoints.
 * Rows are matched on a natural key, so importing the same file twice updates instead of duplicating.
 * Run with $commit = false first to see what would happen; nothing is written until every row is valid.
 */
final class DataImporter
{
    public const TYPES = ['customers' => 'Customers', 'trucks' => 'Trucks', 'drivers' => 'Drivers', 'places' => 'Routes and checkpoints'];

    /** Column names people actually use, mapped to ours. */
    private const ALIASES = [
        'company' => 'name', 'customer' => 'name', 'customer_name' => 'name', 'full_name' => 'name', 'driver' => 'name', 'driver_name' => 'name', 'town' => 'name', 'city' => 'name', 'checkpoint' => 'name',
        'phone_number' => 'phone', 'mobile' => 'phone', 'telephone' => 'phone', 'tel' => 'phone', 'gsm' => 'phone',
        'email_address' => 'email', 'address' => 'billing_address', 'tax_id' => 'tin', 'terms' => 'payment_terms_days', 'payment_terms' => 'payment_terms_days',
        'reg' => 'registration', 'reg_no' => 'registration', 'plate' => 'registration', 'plate_number' => 'registration', 'registration_number' => 'registration',
        'truck_type' => 'type', 'body' => 'type', 'capacity' => 'capacity_tonnes', 'tonnes' => 'capacity_tonnes', 'tonnage' => 'capacity_tonnes', 'tons' => 'capacity_tonnes',
        'tracker' => 'tracker_device_id', 'tracker_id' => 'tracker_device_id', 'insurance_expiry' => 'insurance_expires_on', 'roadworthiness_expiry' => 'roadworthiness_expires_on',
        'licence' => 'licence_number', 'license' => 'licence_number', 'license_number' => 'licence_number', 'licence_expiry' => 'licence_expires_on', 'license_expiry' => 'licence_expires_on',
        'license_expires_on' => 'licence_expires_on', 'smartphone' => 'has_smartphone', 'android' => 'has_smartphone', 'truck' => 'truck_registration', 'usual_truck' => 'truck_registration',
        'latitude' => 'lat', 'longitude' => 'lng', 'lon' => 'lng', 'long' => 'lng', 'checkpoint_town' => 'is_checkpoint',
    ];

    /** @return array<string, list<string>> */
    public static function templates(): array
    {
        return [
            'customers' => ['name', 'phone', 'email', 'billing_address', 'tin', 'payment_terms_days'],
            'trucks' => ['registration', 'type', 'capacity_tonnes', 'make', 'model', 'year', 'tracker_device_id', 'insurance_expires_on', 'roadworthiness_expires_on', 'licence_expires_on'],
            'drivers' => ['name', 'phone', 'licence_number', 'licence_expires_on', 'has_smartphone', 'truck_registration'],
            'places' => ['name', 'state', 'lat', 'lng', 'is_checkpoint'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function examples(): array
    {
        return [
            'customers' => ['Obi Foods Ltd', '0803 123 4567', 'accounts@obifoods.ng', '12 Creek Road, Apapa, Lagos', '12345678-0001', '30'],
            'trucks' => ['LAG-123-XY', 'flatbed', '30', 'MAN', 'TGS', '2019', '', '2027-03-31', '2027-01-15', '2027-06-30'],
            'drivers' => ['Musa Abdullahi', '0803 111 2222', 'LAG12345AA20', '2028-05-01', 'yes', 'LAG-123-XY'],
            'places' => ['Ore', 'Ondo', '6.7470', '4.8790', 'yes'],
        ];
    }

    /**
     * @return array{rows: int, created: int, updated: int, errors: list<string>, committed: bool}
     */
    public function import(string $type, string $path, bool $commit): array
    {
        if (! isset(self::TYPES[$type])) {
            throw new RuntimeException("Unknown import type {$type}.");
        }
        $rows = $this->read($path);
        $report = ['rows' => count($rows), 'created' => 0, 'updated' => 0, 'errors' => [], 'committed' => false];
        $plans = [];
        $seen = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // header is line 1
            try {
                $plan = $this->plan($type, $row);
                if (isset($seen[$plan['key']])) {
                    throw new ImportRowException("same record as row {$seen[$plan['key']]}.");
                }
                $seen[$plan['key']] = $line;
                $plans[] = $plan;
            } catch (ImportRowException $e) {
                $report['errors'][] = "Row {$line}: ".$e->getMessage();
            }
        }
        foreach ($plans as $p) {
            $p['exists'] ? $report['updated']++ : $report['created']++;
        }

        if ($commit && $report['errors'] === [] && $plans !== []) {
            DB::transaction(function () use ($plans): void {
                foreach ($plans as $p) {
                    ($p['save'])();
                }
            });
            $report['committed'] = true;
        }

        return $report;
    }

    /** @return list<array<string, string>> */
    public function read(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = match ($ext) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw new RuntimeException('Upload a .csv or .xlsx file.'),
        };
        $reader->open($path);
        $header = null;
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($v): string => trim($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v), $row->toArray());
                if ($header === null) {
                    $header = array_map(fn (string $h): string => self::column($h), $cells);

                    continue;
                }
                if (implode('', $cells) === '') {
                    continue;
                }
                $assoc = [];
                foreach ($header as $k => $col) {
                    if ($col !== '') {
                        $assoc[$col] = $cells[$k] ?? '';
                    }
                }
                $out[] = $assoc;
            }
            break; // first sheet only
        }
        $reader->close();

        return $out;
    }

    public static function column(string $heading): string
    {
        $c = Str::snake(Str::lower(trim(preg_replace('/[^A-Za-z0-9]+/', ' ', Str::ascii($heading)) ?? '')));
        $c = str_replace(' ', '_', $c);

        return self::ALIASES[$c] ?? $c;
    }

    /**
     * @param  array<string, string>  $r
     * @return array{key: string, exists: bool, save: callable(): bool}
     */
    private function plan(string $type, array $r): array
    {
        return match ($type) {
            'customers' => $this->customer($r),
            'trucks' => $this->truck($r),
            'drivers' => $this->driver($r),
            default => $this->place($r),
        };
    }

    /**
     * @param  array<string, string>  $r
     * @return array{key: string, exists: bool, save: callable(): bool}
     */
    private function customer(array $r): array
    {
        $d = $this->validate($r, [
            'name' => ['required', 'string', 'max:160'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:160'],
            'billing_address' => ['nullable', 'string', 'max:500'], 'tin' => ['nullable', 'string', 'max:40'], 'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:180'],
        ]);
        $phone = filled($d['phone'] ?? null) ? PhoneNumber::normalise($d['phone']) : null;
        $email = filled($d['email'] ?? null) ? Str::lower($d['email']) : null;
        $existing = Customer::query()
            ->where(fn ($q) => $q->where('name', $d['name'])
                ->when($email !== null, fn ($q) => $q->orWhere('email', $email))
                ->when($phone !== null, fn ($q) => $q->orWhere('phone', $phone)))
            ->first();
        $attrs = array_filter(['name' => $d['name'], 'phone' => $phone, 'email' => $email, 'billing_address' => $d['billing_address'] ?? null,
            'tin' => $d['tin'] ?? null, 'payment_terms_days' => isset($d['payment_terms_days']) && $d['payment_terms_days'] !== '' ? (int) $d['payment_terms_days'] : null], fn ($v) => $v !== null && $v !== '');

        return ['key' => Str::lower($d['name']), 'exists' => $existing !== null, 'save' => fn () => ($existing ?? new Customer)->fill($attrs)->save()];
    }

    /**
     * @param  array<string, string>  $r
     * @return array{key: string, exists: bool, save: callable(): bool}
     */
    private function truck(array $r): array
    {
        $r['type'] = $this->truckType($r['type'] ?? '');
        $r['registration'] = strtoupper(preg_replace('/\s+/', '-', trim($r['registration'] ?? '')) ?? '');
        $d = $this->validate($r, [
            'registration' => ['required', 'string', 'max:20'], 'type' => ['required', 'in:'.implode(',', array_keys(Truck::TYPES))],
            'capacity_tonnes' => ['required', 'numeric', 'min:1', 'max:60'], 'make' => ['nullable', 'string', 'max:60'], 'model' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)], 'tracker_device_id' => ['nullable', 'string', 'max:60'],
            'insurance_expires_on' => ['nullable', 'date'], 'roadworthiness_expires_on' => ['nullable', 'date'], 'licence_expires_on' => ['nullable', 'date'],
        ], ['type.in' => 'Truck type must be one of: '.implode(', ', array_keys(Truck::TYPES)).'.']);
        $existing = Truck::query()->where('registration', $d['registration'])->first();
        $attrs = $this->filled($d, ['registration', 'type', 'capacity_tonnes', 'make', 'model', 'year', 'tracker_device_id']) + $this->dates($d, ['insurance_expires_on', 'roadworthiness_expires_on', 'licence_expires_on']);

        return ['key' => $d['registration'], 'exists' => $existing !== null, 'save' => fn () => ($existing ?? new Truck)->fill($attrs)->save()];
    }

    /**
     * @param  array<string, string>  $r
     * @return array{key: string, exists: bool, save: callable(): bool}
     */
    private function driver(array $r): array
    {
        $d = $this->validate($r, [
            'name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'max:30'], 'licence_number' => ['nullable', 'string', 'max:40'],
            'licence_expires_on' => ['nullable', 'date'], 'has_smartphone' => ['nullable', 'string'], 'truck_registration' => ['nullable', 'string', 'max:20'],
        ]);
        $phone = PhoneNumber::normalise($d['phone']);
        if ($phone === null || strlen($phone) !== 13) {
            throw new ImportRowException("'{$d['phone']}' is not a Nigerian phone number.");
        }
        $truckId = null;
        if (filled($d['truck_registration'] ?? null)) {
            $reg = strtoupper(preg_replace('/\s+/', '-', trim($d['truck_registration'])) ?? '');
            $truckId = Truck::query()->where('registration', $reg)->value('id');
            if ($truckId === null) {
                throw new ImportRowException("Truck {$reg} is not in the system. Import trucks first.");
            }
        }
        $existing = Driver::query()->where('phone', $phone)->first();
        $attrs = ['name' => $d['name'], 'phone' => $phone] + $this->filled($d, ['licence_number']) + $this->dates($d, ['licence_expires_on'])
            + (filled($d['has_smartphone'] ?? null) ? ['has_smartphone' => self::yes((string) $d['has_smartphone'])] : [])
            + ($truckId !== null ? ['truck_id' => $truckId] : []);

        return ['key' => $phone, 'exists' => $existing !== null, 'save' => fn () => ($existing ?? new Driver)->fill($attrs)->save()];
    }

    /**
     * @param  array<string, string>  $r
     * @return array{key: string, exists: bool, save: callable(): bool}
     */
    private function place(array $r): array
    {
        $d = $this->validate($r, [
            'name' => ['required', 'string', 'max:80'], 'state' => ['nullable', 'string', 'max:40'],
            'lat' => ['required', 'numeric', 'between:4,14'], 'lng' => ['required', 'numeric', 'between:2.5,15'], 'is_checkpoint' => ['nullable', 'string'],
        ], ['lat.between' => 'Latitude must be inside Nigeria (4 to 14).', 'lng.between' => 'Longitude must be inside Nigeria (2.5 to 15).']);
        $existing = Place::query()->where('name', $d['name'])->where('state', $d['state'] ?? null)->first();
        $attrs = ['name' => $d['name'], 'state' => $d['state'] ?? null, 'lat' => (float) $d['lat'], 'lng' => (float) $d['lng'],
            'is_checkpoint' => filled($d['is_checkpoint'] ?? null) ? self::yes((string) $d['is_checkpoint']) : true];

        return ['key' => Str::lower($d['name'].'|'.($d['state'] ?? '')), 'exists' => $existing !== null, 'save' => fn () => ($existing ?? new Place)->fill($attrs)->save()];
    }

    /**
     * @param  array<string, string>  $r
     * @param  array<string, list<string>>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, string>
     */
    private function validate(array $r, array $rules, array $messages = []): array
    {
        $r = array_map(fn ($v) => $v === '' ? null : $v, $r);
        $v = Validator::make($r, $rules, $messages);
        if ($v->fails()) {
            throw new ImportRowException(implode(' ', $v->errors()->all()));
        }

        /** @var array<string, string> */
        return array_map(fn ($x) => (string) $x, array_filter(array_intersect_key($r, $rules), fn ($x) => $x !== null));
    }

    /**
     * @param  array<string, string>  $d
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function filled(array $d, array $keys): array
    {
        return array_filter(array_intersect_key($d, array_flip($keys)), fn ($v) => $v !== '');
    }

    /**
     * @param  array<string, string>  $d
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function dates(array $d, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (filled($d[$k] ?? null)) {
                $out[$k] = Carbon::parse($d[$k])->toDateString();
            }
        }

        return $out;
    }

    private function truckType(string $raw): string
    {
        $t = Str::snake(Str::lower(trim($raw)));
        foreach (Truck::TYPES as $key => $label) {
            if ($t === $key || $t === Str::snake(Str::lower($label))) {
                return $key;
            }
        }

        return match (true) {
            str_contains($t, 'tank') => 'tanker', str_contains($t, 'tip') => 'tipper', str_contains($t, 'flat') => 'flatbed',
            str_contains($t, 'low') => 'lowbed', str_contains($t, 'box') => 'box_body', str_contains($t, 'curtain') => 'curtainsider',
            str_contains($t, 'container') || str_contains($t, 'skeletal') => 'container', default => $t,
        };
    }

    private static function yes(string $v): bool
    {
        return in_array(Str::lower(trim($v)), ['1', 'yes', 'y', 'true', 'x'], true);
    }
}
