<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/**
 * Naira text field that reads and writes integer kobo. Never uses floats.
 */
final class MoneyInput
{
    public static function make(string $koboField, string $label): TextInput
    {
        return TextInput::make($koboField)
            ->label($label)
            ->prefix('₦')
            ->inputMode('decimal')
            ->rule('regex:/^-?\d{1,3}(,?\d{3})*(\.\d{1,2})?$/')
            ->validationMessages(['regex' => 'Enter an amount like 150,000 or 150000.50.'])
            ->formatStateUsing(fn ($state): ?string => is_numeric($state) ? Money::kobo((int) $state)->toDecimalString() : $state)
            ->dehydrateStateUsing(fn ($state): int => filled($state) ? Money::naira((string) $state)->kobo : 0);
    }
}
