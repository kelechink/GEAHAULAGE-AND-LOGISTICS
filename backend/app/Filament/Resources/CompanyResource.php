<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Crm\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Companies';

    protected static ?int $navigationSort = 30;

    protected static function viewPermission(): Permission
    {
        return Permission::CrmView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::CrmManage;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('industry')->maxLength(255),
                Forms\Components\TextInput::make('address')->maxLength(255),
                Forms\Components\TextInput::make('tin')->label('TIN')->maxLength(30),
                Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('industry')->searchable(),
                Tables\Columns\TextColumn::make('contacts_count')->counts('contacts')->label('Contacts'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
