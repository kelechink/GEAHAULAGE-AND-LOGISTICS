<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * "Three hundred and sixty-five thousand, five hundred naira only" (PRD 10.3), British usage.
 */
final class AmountInWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const SCALES = ['', ' thousand', ' million', ' billion', ' trillion'];

    public static function naira(int $kobo): string
    {
        $neg = $kobo < 0;
        $kobo = abs($kobo);
        $naira = intdiv($kobo, 100);
        $k = $kobo % 100;

        $words = ($naira === 0 ? 'zero' : self::number($naira)).' naira';
        if ($k > 0) {
            $words .= ', '.self::number($k).' kobo';
        }

        return ucfirst(($neg ? 'minus ' : '').$words.' only');
    }

    public static function number(int $n): string
    {
        $chunks = [];
        for ($scale = 0; $n > 0; $scale++, $n = intdiv($n, 1000)) {
            $chunks[$scale] = $n % 1000;
        }

        $parts = [];
        $hasHigher = count($chunks) > 1;
        for ($scale = count($chunks) - 1; $scale >= 0; $scale--) {
            $c = $chunks[$scale];
            if ($c === 0) {
                continue;
            }
            $words = self::hundreds($c).self::SCALES[$scale];
            if ($scale === 0 && $hasHigher && $c < 100) {
                $words = 'and '.$words;
            }
            $parts[] = $words;
        }

        $last = array_pop($parts);
        if ($parts === []) {
            return (string) $last;
        }

        // "one thousand and five", but "one thousand, two hundred".
        return implode(', ', $parts).(str_starts_with((string) $last, 'and ') ? ' ' : ', ').$last;
    }

    private static function hundreds(int $n): string
    {
        $h = intdiv($n, 100);
        $rest = $n % 100;
        $tens = $rest < 20 ? self::ONES[$rest] : self::TENS[intdiv($rest, 10)].($rest % 10 ? '-'.self::ONES[$rest % 10] : '');

        if ($h === 0) {
            return $tens;
        }

        return self::ONES[$h].' hundred'.($rest > 0 ? ' and '.$tens : '');
    }
}
