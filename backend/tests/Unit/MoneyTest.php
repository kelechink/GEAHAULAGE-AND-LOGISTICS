<?php

declare(strict_types=1);

use App\Support\Money;

it('parses naira strings into kobo without floats', function (string $in, int $kobo) {
    expect(Money::naira($in)->kobo)->toBe($kobo);
})->with([
    ['1,234.56', 123456],
    ['₦350000', 35000000],
    ['1000.3', 100030],
    ['0.05', 5],
    ['-10.5', -1050],
]);

it('rejects things that are not amounts', function () {
    Money::naira('12.345');
})->throws(InvalidArgumentException::class);

it('formats kobo for display', function () {
    expect(Money::kobo(36550000)->format())->toBe('₦365,500.00')
        ->and(Money::kobo(-1050)->format())->toBe('-₦10.50')
        ->and(Money::kobo(7502)->toDecimalString())->toBe('75.02');
});

it('rounds percentages half-up to the kobo', function () {
    // ₦1,000.33 at 7.5% = ₦75.02475 → ₦75.02 (PRD 19 rounding case)
    expect(Money::kobo(100033)->percentBasisPoints(750)->kobo)->toBe(7502)
        // ₦0.10 at 5% = 0.5 kobo → rounds up to 1 kobo
        ->and(Money::kobo(10)->percentBasisPoints(500)->kobo)->toBe(1);
});

it('converts percentage strings to basis points', function () {
    expect(Money::basisPoints('7.5'))->toBe(750)
        ->and(Money::basisPoints('0'))->toBe(0)
        ->and(Money::basisPoints(5))->toBe(500);
});
