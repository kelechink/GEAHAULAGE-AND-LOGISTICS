<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * Last line of defence before anything leaves for a free-tier AI provider (PRD 12.1, CLAUDE.md):
 * money amounts and bank account or card numbers are masked. Tools only return public-safe fields to
 * begin with; this catches what visitors type themselves. A visitor's own name, phone and email are kept,
 * because the lead tool needs them.
 */
final class DataGuard
{
    private const PATTERNS = [
        // ₦1,500,000 / N 250k / NGN 40000.50 / 1.5m naira
        '/(?:₦|\bNGN\b|\bN(?=\s?\d))\s?\d[\d,]*(?:\.\d+)?\s?(?:k|m|million|thousand)?\b/iu' => '[amount]',
        '/\b\d[\d,]*(?:\.\d+)?\s?(?:k|m|million|thousand)?\s?(?:naira|kobo)\b/iu' => '[amount]',
        // Card numbers (13–19 digits, optional spaces/dashes)
        '/\b(?:\d[ -]?){13,19}\b/' => '[number]',
        // NUBAN bank account numbers: exactly 10 digits
        '/(?<!\d)\d{10}(?!\d)/' => '[number]',
    ];

    public static function scrub(string $text): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    public static function scrubMessages(array $messages): array
    {
        return array_map(function (array $m): array {
            if (($m['role'] ?? '') !== 'system' && isset($m['content']) && is_string($m['content'])) {
                $m['content'] = self::scrub($m['content']);
            }

            return $m;
        }, $messages);
    }
}
