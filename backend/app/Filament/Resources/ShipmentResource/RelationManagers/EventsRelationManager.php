<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipmentResource\RelationManagers;

use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Timeline';

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
                Tables\Columns\TextColumn::make('to_stage')->label('Stage')->badge()->placeholder('Update')
                    ->formatStateUsing(fn (?ShipmentStage $state): string => $state?->label() ?? 'Update'),
                Tables\Columns\TextColumn::make('place_label')->label('Where')->placeholder('—'),
                Tables\Columns\TextColumn::make('note')->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('source')->formatStateUsing(fn (TrackingSource $state): string => $state->label()),
                Tables\Columns\TextColumn::make('user.name')->label('By')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_public')->label('Customer sees')->boolean(),
            ]);
    }
}
