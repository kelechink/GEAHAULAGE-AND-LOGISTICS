<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\ContactResource\Pages;
use App\Models\Crm\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Contacts';

    protected static ?int $navigationSort = 20;

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
                Forms\Components\Select::make('company_id')->relationship('company', 'name')->searchable()->preload()->createOptionForm([Forms\Components\TextInput::make('name')->required()]),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(20),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('whatsapp')->tel()->maxLength(20),
                Forms\Components\TextInput::make('role')->label('Job title')->maxLength(255),
                Forms\Components\Select::make('source')->options(LeadResource::sourceOptions())->required()->default('phone'),
                Forms\Components\Toggle::make('consent_marketing')->label('Agreed to marketing'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company.name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('leads_count')->counts('leads')->label('Leads'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}
