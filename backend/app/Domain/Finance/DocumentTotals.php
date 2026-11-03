<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Support\Money;

/**
 * PRD 10.3, in integer kobo:
 *   subtotal    = Σ (qty × unit_price)
 *   taxable     = subtotal − discount
 *   vat         = taxable × vat_rate / 100   (half-up)
 *   grand_total = taxable + vat
 *   balance_due = grand_total − amount_paid − wht_deducted − credited
 */
final class DocumentTotals
{
    public function __construct(
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly int $taxable,
        public readonly int $vatRateBp,
        public readonly int $vat,
        public readonly int $total,
        /** @var list<int> */
        public readonly array $lineAmounts,
    ) {}

    /**
     * @param  list<array{quantity: string|int, unit_price_kobo: int}>  $lines
     */
    public static function calculate(array $lines, int $discountKobo, int $vatRateBp): self
    {
        $amounts = [];
        $subtotal = Money::zero();
        foreach ($lines as $line) {
            $amount = Money::kobo($line['unit_price_kobo'])->timesQuantity($line['quantity']);
            $amounts[] = $amount->kobo;
            $subtotal = $subtotal->add($amount);
        }

        if ($discountKobo < 0 || $discountKobo > $subtotal->kobo) {
            throw new FinanceException('The discount must be between ₦0 and the subtotal.');
        }

        $taxable = $subtotal->subtract(Money::kobo($discountKobo));
        $vat = $taxable->percentBasisPoints($vatRateBp);

        return new self($subtotal->kobo, $discountKobo, $taxable->kobo, $vatRateBp, $vat->kobo, $taxable->add($vat)->kobo, $amounts);
    }

    public static function balance(int $total, int $paid, int $wht, int $credited = 0): int
    {
        return $total - $paid - $wht - $credited;
    }
}
