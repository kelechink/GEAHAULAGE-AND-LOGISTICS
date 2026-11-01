<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Domain\Finance\FinanceException;
use App\Domain\Finance\InvoiceService;
use App\Filament\Concerns\DocumentFormParts;
use App\Filament\Resources\InvoiceResource;
use App\Support\Money;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(InvoiceService::class)->saveDraft(null, [
                'customer_id' => $data['customer_id'],
                'template' => $data['template'],
                'shipment_id' => $data['shipment_id'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'discount_kobo' => (int) ($data['discount_kobo'] ?? 0),
                'vat_rate_bp' => Money::basisPoints((string) ($this->data['vat_rate'] ?? '7.5')),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
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
