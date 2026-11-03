<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\PlaceResource\Pages;
use App\Models\Place;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlaceResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Place::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Checkpoints and towns';

    protected static ?int $navigationSort = 80;

    protected static function viewPermission(): Permission
    {
        return Permission::ShipmentsView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::ShipmentsManage;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(120),
                Forms\Components\TextInput::make('state')->maxLength(40),
                Forms\Components\TextInput::make('lat')->label('Latitude')->numeric()->required()->minValue(-2)->maxValue(25),
                Forms\Components\TextInput::make('lng')->label('Longitude')->numeric()->required()->minValue(-20)->maxValue(20),
                Forms\Components\Toggle::make('is_checkpoint')->label('Show in the update console')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('state')->searchable(),
                Tables\Columns\TextColumn::make('lat'),
                Tables\Columns\TextColumn::make('lng'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlaces::route('/'),
            'create' => Pages\CreatePlace::route('/create'),
            'edit' => Pages\EditPlace::route('/{record}/edit'),
        ];
    }
}
