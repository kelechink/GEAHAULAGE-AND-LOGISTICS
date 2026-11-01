<?php

declare(strict_types=1);

use App\Domain\Crm\PhoneNumber;
use App\Domain\Crm\WorkingDays;
use Illuminate\Support\Carbon;

it('normalises Nigerian phone numbers to 234 format', function (string $raw, string $expected) {
    expect(PhoneNumber::normalise($raw))->toBe($expected);
})->with([
    ['0816 216 3235', '2348162163235'],
    ['+234 816 216 3235', '2348162163235'],
    ['2348162163235', '2348162163235'],
    ['816-216-3235', '2348162163235'],
]);

it('returns null for empty input', function () {
    expect(PhoneNumber::normalise(''))->toBeNull()
        ->and(PhoneNumber::normalise(null))->toBeNull();
});

it('skips weekends when working out the next working day', function () {
    expect(WorkingDays::next(Carbon::parse('2026-11-06 10:00', 'Africa/Lagos'))->format('D'))->toBe('Mon')
        ->and(WorkingDays::next(Carbon::parse('2026-11-03 10:00', 'Africa/Lagos'))->format('D'))->toBe('Wed');
});
