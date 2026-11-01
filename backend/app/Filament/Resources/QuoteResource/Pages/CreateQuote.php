<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Domain\Finance\FinanceException;
use App\Domain\Finance\QuoteService;
use App\Filament\Concerns\DocumentFormParts;
use App\Filament\Resources\QuoteResource;
use App\Support\Money;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(QuoteService::class)->save(null, array_filter([
                'customer_id' => $data['customer_id'],
                'lead_id' => $data['lead_id'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'discount_kobo' => (int) ($data['discount_kobo'] ?? 0),
                'vat_rate_bp' => Money::basisPoints((string) ($this->data['vat_rate'] ?? '7.5')),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ], fn ($v) => $v !== null), DocumentFormParts::linesFromState($data['lines'] ?? []), auth()->user());
        } catch (FinanceException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw new Halt;
        }
    }
}
