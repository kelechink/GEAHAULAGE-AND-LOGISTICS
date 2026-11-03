<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Domain\Tracking\Geocoder;
use App\Filament\Resources\ShipmentResource;
use Filament\Resources\Pages\EditRecord;

class EditShipment extends EditRecord
{
    protected static string $resource = ShipmentResource::class;

    /**
     * Re-geocode a town when it changes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $geo = app(Geocoder::class);
        foreach (['origin', 'destination'] as $end) {
            if (($data[$end] ?? null) !== $this->getRecord()->getAttribute($end)) {
                $hit = $geo->find((string) $data[$end]);
                $data["{$end}_lat"] = $hit['lat'] ?? null;
                $data["{$end}_lng"] = $hit['lng'] ?? null;
            }
        }

        return $data;
    }
}
