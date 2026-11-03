<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit log (PRD Section 4).
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    protected static ?int $navigationSort = 90;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('causer.name')->label('By')->placeholder('System'),
                Tables\Columns\TextColumn::make('event')->badge(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(static fn (?string $state): string => $state === null ? '' : class_basename($state)),
                Tables\Columns\TextColumn::make('subject_id')->label('ID'),
                Tables\Columns\TextColumn::make('description')->limit(60)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('created_at')->label('When')->dateTime(),
            Infolists\Components\TextEntry::make('causer.name')->label('By')->placeholder('System'),
            Infolists\Components\TextEntry::make('event'),
            Infolists\Components\TextEntry::make('subject_type')->label('Record type'),
            Infolists\Components\TextEntry::make('subject_id')->label('Record ID'),
            Infolists\Components\KeyValueEntry::make('properties.old')->label('Before')->columnSpanFull(),
            Infolists\Components\KeyValueEntry::make('properties.attributes')->label('After')->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }
}
