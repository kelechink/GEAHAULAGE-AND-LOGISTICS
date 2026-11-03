<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Auth\TwoFactor;
use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Users and roles';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\Select::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        static fn ($record): string => Role::tryFrom($record->name)?->label() ?? $record->name,
                    )
                    ->multiple()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->required(static fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(static fn (?string $state): bool => filled($state))
                    ->helperText('At least 12 characters. Leave blank to keep the current password.'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Inactive users cannot sign in. Users are never deleted, so the audit log stays complete.')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(static fn (string $state): string => Role::tryFrom($state)?->label() ?? $state),
                Tables\Columns\IconColumn::make('two_factor_confirmed_at')->label('2FA')->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->hasTwoFactor()),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(
                        static fn ($record): string => Role::tryFrom($record->name)?->label() ?? $record->name,
                    ),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Lost phone and no recovery codes: the user sets up two-factor again at next sign-in.
                Tables\Actions\Action::make('resetTwoFactor')->label('Reset two-factor')->icon('heroicon-o-shield-exclamation')->color('warning')
                    ->visible(fn (User $record): bool => $record->hasTwoFactor())
                    ->requiresConfirmation()
                    ->modalDescription('They will have to scan a new QR code the next time they sign in. Only do this after checking who is asking.')
                    ->action(function (User $record): void {
                        app(TwoFactor::class)->reset($record, auth()->user());
                        Notification::make()->success()->title("Two-factor reset for {$record->name}.")->send();
                    }),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
