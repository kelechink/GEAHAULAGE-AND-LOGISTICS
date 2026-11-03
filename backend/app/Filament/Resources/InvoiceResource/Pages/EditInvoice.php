<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Domain\Finance\FinanceException;
use App\Domain\Finance\InvoiceService;
use App\Filament\Concerns\DocumentFormParts;
use App\Filament\Resources\InvoiceResource;
use App\Models\Finance\Invoice;
use App\Support\Money;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();
        $data['lines'] = $invoice->lines()->get()->map(fn ($l): array => [
            'description' => $l->description, 'quantity' => rtrim(rtrim((string) $l->quantity, '0'), '.'), 'unit_price_kobo' => $l->unit_price_kobo,
        ])->all();
        $data['vat_rate'] = rtrim(rtrim(number_format($invoice->vat_rate_bp / 100, 2, '.', ''), '0'), '.');

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Invoice $record */
        try {
            return app(InvoiceService::class)->saveDraft($record, [
                'customer_id' => $data['customer_id'],
                'template' => $data['template'],
                'shipment_id' => $data['shipment_id'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'discount_kobo' => (int) ($data['discount_kobo'] ?? 0),
                'vat_rate_bp' => Money::basisPoints((string) ($this->data['vat_rate'] ?? '7.5')),
                'notes' => $data['notes'] ?? null,
            ], DocumentFormParts::linesFromState($data['lines'] ?? []));
        } catch (FinanceException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
