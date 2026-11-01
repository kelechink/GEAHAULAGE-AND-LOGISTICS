<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\KnowledgeItemResource\Pages;
use App\Models\Ai\KnowledgeItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** What the assistant is allowed to say (PRD 12.2). */
class KnowledgeItemResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = KnowledgeItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Assistant';

    protected static ?string $navigationLabel = 'Knowledge';

    protected static ?string $modelLabel = 'knowledge item';

    protected static ?int $navigationSort = 30;

    protected static function viewPermission(): Permission
    {
        return Permission::AiManage;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::AiManage;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('category')->options(KnowledgeItem::CATEGORIES)->required(),
                Forms\Components\TextInput::make('title')->required()->maxLength(160),
                Forms\Components\TextInput::make('keywords')->maxLength(250)->columnSpanFull()
                    ->helperText('Words visitors might use, comma-separated. Helps the assistant find this item.'),
                Forms\Components\Textarea::make('body')->label('What the assistant may say')->rows(6)->required()->columnSpanFull()
                    ->helperText('Facts only. Never put prices, bank details or customer names here.'),
                Forms\Components\TextInput::make('sort')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->label('In use')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('category')->badge()->formatStateUsing(fn (string $state): string => KnowledgeItem::CATEGORIES[$state] ?? $state),
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('body')->limit(90)->searchable()->color('gray'),
                Tables\Columns\IconColumn::make('is_active')->label('In use')->boolean(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('category')->options(KnowledgeItem::CATEGORIES)])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKnowledgeItems::route('/'),
            'create' => Pages\CreateKnowledgeItem::route('/create'),
            'edit' => Pages\EditKnowledgeItem::route('/{record}/edit'),
        ];
    }
}
