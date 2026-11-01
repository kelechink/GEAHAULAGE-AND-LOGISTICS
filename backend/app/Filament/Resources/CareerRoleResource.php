<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\CareerRoleResource\Pages;
use App\Models\CareerRole;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CareerRoleResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = CareerRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Career roles';

    protected static ?int $navigationSort = 30;

    protected static function viewPermission(): Permission
    {
        return Permission::ContentManage;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::ContentManage;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->required()->rows(3),
                Forms\Components\Toggle::make('is_open')->label('Accepting applications')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\IconColumn::make('is_open')->label('Open')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCareerRoles::route('/'),
            'create' => Pages\CreateCareerRole::route('/create'),
            'edit' => Pages\EditCareerRole::route('/{record}/edit'),
        ];
    }
}
