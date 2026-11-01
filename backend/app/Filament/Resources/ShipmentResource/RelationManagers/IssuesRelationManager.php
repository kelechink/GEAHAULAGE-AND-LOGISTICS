<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipmentResource\RelationManagers;

use App\Enums\Permission;
use App\Models\DriverIssue;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Breakdowns, accidents, delays and checkpoints sent from the driver app (PRD 13). */
class IssuesRelationManager extends RelationManager
{
    protected static string $relationship = 'issues';

    protected static ?string $title = 'Driver reports';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->label('When')->dateTime('D j M, g:ia', 'Africa/Lagos'),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state): string => DriverIssue::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'breakdown', 'accident' => 'danger', 'delay' => 'warning', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('note')->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('driver.name')->label('Driver'),
                Tables\Columns\TextColumn::make('resolved_at')->label('Resolved')->dateTime('j M, g:ia', 'Africa/Lagos')->placeholder('Open'),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')->label('Mark resolved')->icon('heroicon-o-check')
                    ->visible(fn (DriverIssue $record): bool => $record->resolved_at === null
                        && (auth()->user()?->can(Permission::TrackingUpdate->value) ?? false))
                    ->action(fn (DriverIssue $record) => $record->forceFill(['resolved_at' => now()])->save()),
            ]);
    }
}
