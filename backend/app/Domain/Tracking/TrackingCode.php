<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Models\Shipment;
use Illuminate\Support\Carbon;

/**
 * Tracking codes look like GEA-2611-K7QXM: year and month, then five random characters
 * with no look-alikes (no 0/O, 1/I/L) so they are easy to read out over the phone (PRD 8.5).
 */
final class TrackingCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const PATTERN = '/^GEA-\d{4}-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{5}$/';

    public static function generate(?Carbon $at = null): string
    {
        $prefix = 'GEA-'.($at ?? now())->format('ym').'-';
        do {
            $suffix = '';
            for ($i = 0; $i < 5; $i++) {
                $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = $prefix.$suffix;
        } while (Shipment::query()->where('tracking_code', $code)->exists());

        return $code;
    }

    public static function normalise(string $input): string
    {
        $code = strtoupper((string) preg_replace('/\s+/', '', $input));
        // Accept "GEA2611K7QXM" or "gea-2611-k7qxm".
        if (preg_match('/^GEA-?(\d{4})-?([A-Z0-9]{5})$/', $code, $m)) {
            return "GEA-{$m[1]}-{$m[2]}";
        }

        return $code;
    }

    public static function isValid(string $code): bool
    {
        return (bool) preg_match(self::PATTERN, $code);
    }
}
