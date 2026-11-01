<?php

declare(strict_types=1);

namespace App\Filament\Resources\CareerRoleResource\Pages;

use App\Filament\Resources\CareerRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCareerRoles extends ListRecords
{
    protected static string $resource = CareerRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
