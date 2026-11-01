<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Enums\ShipmentStage;
use App\Models\Shipment;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Active shipments by stage (PRD 10.1).
 */
class DispatchBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Dispatch board';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.dispatch-board';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ShipmentsView->value) ?? false;
    }

    /** @return array<string, array{label: string, items: Collection<int, Shipment>}> */
    public function getColumns(): array
    {
        $active = Shipment::query()->active()->with(['customer', 'truck', 'driver'])->orderBy('pickup_date')->get()
            ->groupBy(fn (Shipment $s): string => $s->stage === ShipmentStage::Delayed ? 'in_transit' : $s->stage->value);

        $cols = [];
        foreach ([ShipmentStage::Booked, ShipmentStage::Assigned, ShipmentStage::Loading, ShipmentStage::Loaded, ShipmentStage::InTransit, ShipmentStage::Arrived] as $stage) {
            /** @var Collection<int, Shipment> $items */
            $items = $active->get($stage->value, collect());
            $cols[$stage->value] = ['label' => $stage === ShipmentStage::InTransit ? 'In transit / delayed' : $stage->label(), 'items' => $items];
        }

        return $cols;
    }
}
