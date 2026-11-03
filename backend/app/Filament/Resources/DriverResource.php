<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\DriverResource\Pages;
use App\Models\Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class DriverResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Fleet';

    protected static ?string $navigationLabel = 'Drivers';

    protected static ?int $navigationSort = 20;

    protected static function viewPermission(): Permission
    {
        return Permission::FleetView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::DriversManage;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(120),
                Forms\Components\TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true)->maxLength(20),
                Forms\Components\TextInput::make('licence_number')->maxLength(40),
                Forms\Components\DatePicker::make('licence_expires_on')->label('Licence expires'),
                Forms\Components\Select::make('truck_id')->label('Usual truck')->relationship('truck', 'registration')->searchable()->preload(),
                Forms\Components\Toggle::make('has_smartphone')->label('Has an Android smartphone')->helperText('Decides whether trips are tracked by the app or by staff updates.'),
                Forms\Components\TextInput::make('guarantor_name')->maxLength(120),
                Forms\Components\TextInput::make('guarantor_phone')->tel()->maxLength(20),
                Forms\Components\FileUpload::make('photo_path')->label('Photo')->image()->disk('public')->directory('drivers')->maxSize(4096),
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('truck.registration')->label('Usual truck')->placeholder('—'),
                Tables\Columns\IconColumn::make('has_smartphone')->label('App')->boolean(),
                Tables\Columns\TextColumn::make('licence_expires_on')->label('Licence expires')->date('j M Y')->color(fn ($state): ?string => $state !== null && $state->lte(now()->addDays(30)) ? 'danger' : null),
                Tables\Columns\TextColumn::make('app_last_seen_at')->label('App last seen')->since()->placeholder('Never')
                    ->description(fn (Driver $record): ?string => $record->app_version !== null ? 'v'.$record->app_version : null),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Operations sets a 4 to 6 digit PIN and reads it to the driver; it is stored hashed (PRD 13).
                Tables\Actions\Action::make('setPin')->label('Set app PIN')->icon('heroicon-o-key')
                    ->visible(fn (): bool => self::allows(Permission::DriversManage))
                    ->form([
                        Forms\Components\TextInput::make('pin')->label('New PIN')->password()->revealable()->required()
                            ->regex('/^\d{4,6}$/')->validationMessages(['regex' => 'Use 4 to 6 digits.']),
                    ])
                    ->action(function (Driver $record, array $data): void {
                        $record->forceFill(['pin_hash' => Hash::make($data['pin'])])->save();
                        $record->tokens()->delete(); // sign out the old phone
                        Notification::make()->success()->title("PIN set for {$record->name}. Old app sessions were signed out.")->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
