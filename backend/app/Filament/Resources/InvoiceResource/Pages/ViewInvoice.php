<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Finance\Invoice;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        return [...InvoiceResource::viewActions($invoice), EditAction::make()];
    }
}
