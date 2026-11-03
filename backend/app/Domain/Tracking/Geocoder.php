<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Models\Place;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Town name → coordinates. Checks GEA's own places list first, then a cache, then Nominatim
 * (light use only, per the OSM usage policy, PRD 5).
 */
final class Geocoder
{
    /** @return array{lat: float, lng: float, label: string}|null */
    public function find(string $query): ?array
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        $first = trim(explode(',', $q)[0]);
        $place = Place::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($first)])->first();
        if ($place !== null) {
            return ['lat' => (float) $place->lat, 'lng' => (float) $place->lng, 'label' => $place->label()];
        }

        $key = mb_strtolower($q);
        $cached = DB::table('geocode_cache')->where('query', $key)->first();
        if ($cached !== null) {
            return $cached->lat === null ? null : ['lat' => (float) $cached->lat, 'lng' => (float) $cached->lng, 'label' => (string) $cached->label];
        }

        $result = null;
        try {
            $response = Http::withHeaders(['User-Agent' => config('app.name').' ('.config('app.url').')'])
                ->timeout(8)
                ->get('https://nominatim.openstreetmap.org/search', ['q' => $q, 'countrycodes' => 'ng,bj,tg,gh,ci,ne', 'format' => 'json', 'limit' => 1]);
            $hit = $response->successful() ? ($response->json()[0] ?? null) : null;
            if (is_array($hit) && isset($hit['lat'], $hit['lon'])) {
                $result = ['lat' => (float) $hit['lat'], 'lng' => (float) $hit['lon'], 'label' => (string) explode(',', (string) ($hit['display_name'] ?? $q))[0]];
            }
        } catch (Throwable) {
            return null; // Network trouble: do not cache a miss.
        }

        DB::table('geocode_cache')->insert([
            'query' => $key, 'lat' => $result['lat'] ?? null, 'lng' => $result['lng'] ?? null, 'label' => $result['label'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $result;
    }

    /** Nearest known place within 25 km, for labelling GPS points ("near Ore, Ondo"). */
    public function nearestPlace(float $lat, float $lng): ?string
    {
        $best = null;
        $bestKm = 25.0;
        foreach (Place::query()->whereBetween('lat', [$lat - 0.3, $lat + 0.3])->whereBetween('lng', [$lng - 0.3, $lng + 0.3])->get() as $p) {
            $km = Geo::km($lat, $lng, (float) $p->lat, (float) $p->lng);
            if ($km < $bestKm) {
                $bestKm = $km;
                $best = $p;
            }
        }

        return $best?->label();
    }
}
