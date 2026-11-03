<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\JobApplicationResource\Pages;
use App\Models\JobApplication;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JobApplicationResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = JobApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Fleet';

    protected static ?string $navigationLabel = 'Job applications';

    protected static function viewPermission(): Permission
    {
        return Permission::DriversManage;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Received')->since()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->copyable(),
                Tables\Columns\TextColumn::make('role')->badge(),
                Tables\Columns\TextColumn::make('years_experience')->label('Years'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('role')->options(fn () => JobApplication::query()->distinct()->pluck('role', 'role')->all())])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('name'),
            Infolists\Components\TextEntry::make('phone')->copyable(),
            Infolists\Components\TextEntry::make('role'),
            Infolists\Components\TextEntry::make('years_experience')->label('Years of experience')->placeholder('Not given'),
            Infolists\Components\TextEntry::make('licence_class')->label('Licence class')->placeholder('Not given'),
            Infolists\Components\TextEntry::make('created_at')->label('Received')->dateTime(),
            Infolists\Components\TextEntry::make('about')->columnSpanFull()->placeholder('Not given'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobApplications::route('/'),
            'view' => Pages\ViewJobApplication::route('/{record}'),
        ];
    }
}
