<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Resources\ShipmentResource;
use App\Models\Shipment;
use Filament\Pages\Page;

/**
 * All active trucks at their last known position, with the age of that update (PRD 10.1).
 */
class LiveMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Live map';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.live-map';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ShipmentsView->value) ?? false;
    }

    /** @return list<array<string, mixed>> */
    public function getMarkers(): array
    {
        return Shipment::query()->active()->with(['latestPosition', 'truck'])->get()
            ->filter(fn (Shipment $s): bool => $s->latestPosition !== null)
            ->map(fn (Shipment $s): array => [
                'lat' => (float) $s->latestPosition->lat,
                'lng' => (float) $s->latestPosition->lng,
                'code' => $s->tracking_code,
                'truck' => $s->truck?->registration,
                'route' => "{$s->origin} → {$s->destination}",
                'stage' => $s->stage->label(),
                'age' => $s->last_update_at?->diffForHumans() ?? 'never',
                'stale' => $s->isStale(),
                'url' => ShipmentResource::getUrl('view', ['record' => $s]),
            ])->values()->all();
    }
}
