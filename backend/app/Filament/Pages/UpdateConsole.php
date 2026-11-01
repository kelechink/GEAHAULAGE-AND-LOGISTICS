<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Resources\ShipmentResource;
use App\Models\Shipment;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * One screen of active trips with a two-click update each (PRD 10.1): click Update, pick a
 * checkpoint or stage, save. Stale trips are listed first.
 */
class UpdateConsole extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Update console';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.update-console';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::TrackingUpdate->value) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Shipment::query()->active()->whereNotIn('stage', ['booked'])->with(['truck', 'driver', 'latestPosition']))
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw('CASE WHEN last_update_at IS NULL THEN 0 ELSE 1 END')->orderBy('last_update_at'))
            ->poll('60s')
            ->columns([
                Tables\Columns\TextColumn::make('tracking_code')->label('Code')->weight('bold')
                    ->url(fn (Shipment $r): string => ShipmentResource::getUrl('view', ['record' => $r])),
                Tables\Columns\TextColumn::make('route')->state(fn (Shipment $r): string => "{$r->origin} → {$r->destination}"),
                Tables\Columns\TextColumn::make('stage')->badge()->formatStateUsing(fn ($state): string => $state->label()),
                Tables\Columns\TextColumn::make('driver.name')->label('Driver')->description(fn (Shipment $r): ?string => $r->driver?->phone),
                Tables\Columns\IconColumn::make('driver.has_smartphone')->label('App')->boolean(),
                Tables\Columns\TextColumn::make('latestPosition.place_label')->label('Last seen')->placeholder('—'),
                Tables\Columns\TextColumn::make('last_update_at')->label('Updated')->since()
                    ->color(fn (Shipment $r): ?string => $r->isStale() ? 'danger' : null)->placeholder('Never'),
            ])
            ->actions([ShipmentResource::updateAction(Tables\Actions\Action::make('update'))->button()]);
    }
}
