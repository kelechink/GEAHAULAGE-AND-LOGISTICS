<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Seeder;

/**
 * Towns and checkpoints on GEA's main corridors (PRD 8.3). Safe in production; staff can edit the list.
 */
class PlacesSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: float, 3: float}> */
    public const PLACES = [
        ['Lagos', 'Lagos', 6.5244, 3.3792], ['Apapa', 'Lagos', 6.4474, 3.3640], ['Ikeja', 'Lagos', 6.6018, 3.3515],
        ['Alagbado', 'Lagos', 6.6667, 3.2667], ['Sagamu Interchange', 'Ogun', 6.8322, 3.6319], ['Ibadan', 'Oyo', 7.3775, 3.9470],
        ['Ogbomosho', 'Oyo', 8.1335, 4.2402], ['Ilorin', 'Kwara', 8.4966, 4.5421], ['Jebba', 'Kwara', 9.1333, 4.8333],
        ['Mokwa', 'Niger', 9.2950, 5.0544], ['Bida', 'Niger', 9.0833, 6.0167], ['Kaduna', 'Kaduna', 10.5105, 7.4165],
        ['Zaria', 'Kaduna', 11.0855, 7.7199], ['Kano', 'Kano', 12.0022, 8.5920], ['Abuja', 'FCT', 9.0765, 7.3986],
        ['Lokoja', 'Kogi', 7.8023, 6.7333], ['Okene', 'Kogi', 7.5512, 6.2359], ['Ore', 'Ondo', 6.7469, 4.8767],
        ['Benin City', 'Edo', 6.3350, 5.6037], ['Asaba', 'Delta', 6.1981, 6.7344], ['Onitsha', 'Anambra', 6.1449, 6.7885],
        ['Enugu', 'Enugu', 6.4584, 7.5464], ['Owerri', 'Imo', 5.4840, 7.0351], ['Aba', 'Abia', 5.1066, 7.3667],
        ['Port Harcourt', 'Rivers', 4.8156, 7.0498], ['Warri', 'Delta', 5.5167, 5.7500], ['Jos', 'Plateau', 9.8965, 8.8583],
        ['Bauchi', 'Bauchi', 10.3158, 9.8442], ['Maiduguri', 'Borno', 11.8333, 13.1500], ['Sokoto', 'Sokoto', 13.0059, 5.2476],
        ['Abeokuta', 'Ogun', 7.1475, 3.3619], ['Akure', 'Ondo', 7.2571, 5.2058],
    ];

    public function run(): void
    {
        foreach (self::PLACES as [$name, $state, $lat, $lng]) {
            Place::query()->firstOrCreate(['name' => $name, 'state' => $state], ['lat' => $lat, 'lng' => $lng]);
        }
    }
}
