<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Naira amounts held as integer kobo. No floats anywhere (PRD 10.3).
 */
final class Money
{
    private function __construct(public readonly int $kobo) {}

    public static function kobo(int $kobo): self
    {
        return new self($kobo);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parses "1,234.5", "₦1234.56" or "1234" into kobo without floating point.
     */
    public static function naira(string|int $naira): self
    {
        if (is_int($naira)) {
            return new self($naira * 100);
        }

        $clean = str_replace([',', '₦', 'NGN', ' '], '', trim($naira));
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $clean, $m)) {
            throw new InvalidArgumentException("Not a naira amount: {$naira}");
        }

        $kobo = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return new self($m[1] === '-' ? -$kobo : $kobo);
    }

    public function add(self $other): self
    {
        return new self($this->kobo + $other->kobo);
    }

    public function subtract(self $other): self
    {
        return new self($this->kobo - $other->kobo);
    }

    public function multiply(int $times): self
    {
        return new self($this->kobo * $times);
    }

    /**
     * Unit price × a decimal quantity such as "2.5" or "12.375", rounded half-up to the kobo.
     */
    public function timesQuantity(string|int $quantity): self
    {
        $q = (string) $quantity;
        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', trim($q), $m)) {
            throw new InvalidArgumentException("Not a quantity: {$q}");
        }
        $milli = (int) $m[1] * 1000 + (int) str_pad($m[2] ?? '0', 3, '0');
        $num = $this->kobo * $milli;
        $sign = $num < 0 ? -1 : 1;

        return new self($sign * intdiv(abs($num) + 500, 1000));
    }

    /**
     * Percentage of this amount, rounded half-up to the nearest kobo. Rate is given in basis points
     * (7.5% = 750) so the whole calculation stays in integers.
     */
    public function percentBasisPoints(int $basisPoints): self
    {
        $num = $this->kobo * $basisPoints;
        $sign = $num < 0 ? -1 : 1;

        return new self($sign * intdiv(abs($num) + 5000, 10000));
    }

    public function isZero(): bool
    {
        return $this->kobo === 0;
    }

    public function isNegative(): bool
    {
        return $this->kobo < 0;
    }

    /** "1234.56" for form fields. */
    public function toDecimalString(): string
    {
        $abs = abs($this->kobo);

        return ($this->kobo < 0 ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** "₦1,234.56" for display. */
    public function format(bool $symbol = true): string
    {
        $abs = abs($this->kobo);
        $naira = number_format(intdiv($abs, 100));

        return ($this->kobo < 0 ? '-' : '').($symbol ? '₦' : '').$naira.'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Converts a percentage string such as "7.5" into basis points (750) without floats. */
    public static function basisPoints(string|int $percent): int
    {
        if (is_int($percent)) {
            return $percent * 100;
        }
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($percent), $m)) {
            throw new InvalidArgumentException("Not a percentage: {$percent}");
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
}
