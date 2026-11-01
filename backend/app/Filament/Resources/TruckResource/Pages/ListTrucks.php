<?php

declare(strict_types=1);

namespace App\Filament\Resources\TruckResource\Pages;

use App\Filament\Resources\TruckResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrucks extends ListRecords
{
    protected static string $resource = TruckResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
