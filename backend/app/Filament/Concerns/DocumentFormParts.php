<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Domain\Finance\DocumentTotals;
use App\Filament\Forms\MoneyInput;
use App\Support\Money;
use Filament\Forms;
use Throwable;

/**
 * Line items and live totals shared by the invoice and quote forms.
 */
final class DocumentFormParts
{
    public static function lines(): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('lines')
            ->label('Lines')
            ->schema([
                Forms\Components\TextInput::make('description')->required()->maxLength(255)->columnSpan(6),
                Forms\Components\TextInput::make('quantity')->required()->default('1')->rule('regex:/^\d+(\.\d{1,3})?$/')
                    ->validationMessages(['regex' => 'Use a number like 1 or 2.5.'])->live(onBlur: true)->columnSpan(2),
                MoneyInput::make('unit_price_kobo', 'Unit price')->required()->live(onBlur: true)->columnSpan(4),
            ])
            ->columns(12)
            ->minItems(1)
            ->defaultItems(1)
            ->reorderable()
            ->addActionLabel('Add line')
            ->live();
    }

    public static function vatRate(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('vat_rate')
            ->label('VAT rate')->suffix('%')->required()->default('7.5')
            ->rule('regex:/^\d{1,2}(\.\d{1,2})?$/')->helperText('0 for VAT-exempt.')
            ->live(onBlur: true)->dehydrated(false);
    }

    /** Live preview of the totals using the same integer maths as the server. */
    public static function totals(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('totals_preview')
            ->label('Totals')
            ->content(function (Forms\Get $get): string {
                try {
                    $lines = [];
                    foreach ((array) $get('lines') as $l) {
                        if (! is_array($l) || blank($l['unit_price_kobo'] ?? null) || blank($l['quantity'] ?? null)) {
                            continue;
                        }
                        $lines[] = ['quantity' => (string) $l['quantity'], 'unit_price_kobo' => Money::naira((string) $l['unit_price_kobo'])->kobo];
                    }
                    $discount = filled($get('discount_kobo')) ? Money::naira((string) $get('discount_kobo'))->kobo : 0;
                    $t = DocumentTotals::calculate($lines, $discount, Money::basisPoints((string) ($get('vat_rate') ?: '0')));

                    return 'Subtotal '.Money::kobo($t->subtotal)->format().' · VAT '.Money::kobo($t->vat)->format().' · Total '.Money::kobo($t->total)->format();
                } catch (Throwable $e) {
                    return 'Check the amounts: '.$e->getMessage();
                }
            });
    }

    /**
     * Turns repeater state into service input (kobo already dehydrated by MoneyInput).
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<array{description: string, quantity: string, unit_price_kobo: int}>
     */
    public static function linesFromState(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $price = $r['unit_price_kobo'] ?? 0;
            $out[] = [
                'description' => (string) ($r['description'] ?? ''),
                'quantity' => (string) ($r['quantity'] ?? '1'),
                'unit_price_kobo' => is_int($price) ? $price : Money::naira((string) $price)->kobo,
            ];
        }

        return $out;
    }
}
