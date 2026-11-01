<?php

declare(strict_types=1);

namespace App\Domain\Crm;

/**
 * Normalises Nigerian phone numbers to 234XXXXXXXXXX so the same caller matches one contact.
 */
final class PhoneNumber
{
    public static function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '234')) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '234'.$digits;
        }

        return $digits;
    }
}
