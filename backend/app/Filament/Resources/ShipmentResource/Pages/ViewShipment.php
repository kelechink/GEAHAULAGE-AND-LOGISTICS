<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Domain\Shipping\ShipmentService;
use App\Domain\Shipping\ShippingException;
use App\Enums\Permission;
use App\Filament\Resources\ShipmentResource;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Truck;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewShipment extends ViewRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Shipment $shipment */
        $shipment = $this->getRecord();

        return [
            ShipmentResource::updateAction(Actions\Action::make('update'))->after(fn () => $this->refreshFormData(['stage'])),
            Actions\Action::make('assign')
                ->label($shipment->truck_id ? 'Change truck' : 'Assign truck')
                ->icon('heroicon-o-truck')
                ->visible(fn (): bool => $shipment->stage->isActive() && in_array($shipment->stage->value, ['booked', 'assigned'], true)
                    && (auth()->user()?->can(Permission::ShipmentsManage->value) ?? false))
                ->form([
                    Forms\Components\Select::make('truck_id')->label('Truck')->required()->searchable()
                        ->options(fn () => ShipmentResource::truckOptions($shipment, $shipment->tonnage)),
                    Forms\Components\Select::make('driver_id')->label('Driver')->searchable()
                        ->options(fn () => Driver::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->helperText("Leave empty to use the truck's usual driver."),
                ])
                ->action(function (array $data) use ($shipment): void {
                    try {
                        app(ShipmentService::class)->assign($shipment, Truck::query()->findOrFail($data['truck_id']),
                            filled($data['driver_id'] ?? null) ? Driver::query()->find($data['driver_id']) : null, auth()->user());
                        Notification::make()->title('Truck assigned')->success()->send();
                    } catch (ShippingException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('emailLink')
                ->label('Email tracking link')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn (): bool => filled($shipment->customer->email))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Send the booking details and tracking link to '.$shipment->customer->email.'?')
                ->action(function () use ($shipment): void {
                    app(ShipmentService::class)->emailTrackingLink($shipment);
                    Notification::make()->success()->title('Tracking link emailed to '.$shipment->customer->email.'.')->send();
                }),
            Actions\Action::make('whatsapp')
                ->label('WhatsApp tracking link')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(fn (): string => 'https://wa.me/'.ltrim((string) ($shipment->customer->phone ?? ''), '+').'?text='.rawurlencode(ShipmentResource::whatsappMessage($shipment)), shouldOpenInNewTab: true),
            Actions\Action::make('public')
                ->label('Customer view')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => route('track.show', $shipment->tracking_code), shouldOpenInNewTab: true),
            Actions\EditAction::make(),
        ];
    }
}
