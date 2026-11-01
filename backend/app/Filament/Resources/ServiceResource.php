<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Services';

    protected static ?int $navigationSort = 10;

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
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\TextInput::make('short_title')->label('Short title')->helperText('Used in the footer and page titles.')->required()->maxLength(60),
                Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash()->maxLength(80)
                    ->helperText('The page address: /services/your-slug. Changing it breaks old links.'),
                Forms\Components\Select::make('media')->options(Service::MEDIA)->required(),
                Forms\Components\Textarea::make('summary')->required()->maxLength(300)->rows(2)->columnSpanFull(),
                Forms\Components\Textarea::make('body')->required()->rows(6)->columnSpanFull()
                    ->helperText('Leave a blank line between paragraphs.'),
                Forms\Components\TagsInput::make('highlights')->columnSpanFull()->placeholder('Add a highlight'),
                Forms\Components\TextInput::make('sort')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->label('Show on website')->default(true),
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
                Tables\Columns\TextColumn::make('slug')->color('gray'),
                Tables\Columns\IconColumn::make('is_active')->label('On website')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
