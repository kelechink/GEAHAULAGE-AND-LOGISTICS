<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Domain\Finance\FinanceException;
use App\Domain\Finance\QuoteService;
use App\Filament\Concerns\DocumentFormParts;
use App\Filament\Resources\QuoteResource;
use App\Models\Finance\Quote;
use App\Support\Money;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Quote $q */
        $q = $this->getRecord();
        $data['lines'] = $q->lines()->get()->map(fn ($l): array => [
            'description' => $l->description, 'quantity' => rtrim(rtrim((string) $l->quantity, '0'), '.'), 'unit_price_kobo' => $l->unit_price_kobo,
        ])->all();
        $data['vat_rate'] = rtrim(rtrim(number_format($q->vat_rate_bp / 100, 2, '.', ''), '0'), '.');

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Quote $record */
        try {
            return app(QuoteService::class)->save($record, array_filter([
                'customer_id' => $data['customer_id'],
                'lead_id' => $data['lead_id'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'discount_kobo' => (int) ($data['discount_kobo'] ?? 0),
                'vat_rate_bp' => Money::basisPoints((string) ($this->data['vat_rate'] ?? '7.5')),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ], fn ($v) => $v !== null), DocumentFormParts::linesFromState($data['lines'] ?? []));
        } catch (FinanceException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw new Halt;
        }
    }
}
