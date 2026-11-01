<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Domain\Shipping\ShipmentService;
use App\Filament\Resources\ShipmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateShipment extends CreateRecord
{
    protected static string $resource = ShipmentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ShipmentService::class)->create(array_filter($data, fn ($v) => $v !== null && $v !== ''), auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
