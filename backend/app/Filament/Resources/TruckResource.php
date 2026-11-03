<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Permission;
use App\Enums\TruckStatus;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\TruckResource\Pages;
use App\Models\Truck;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TruckResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Truck::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Fleet';

    protected static ?string $navigationLabel = 'Trucks';

    protected static ?int $navigationSort = 10;

    protected static function viewPermission(): Permission
    {
        return Permission::FleetView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::FleetManage;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('registration')->required()->unique(ignoreRecord: true)->maxLength(20)->placeholder('e.g. LAG-123-XY'),
                Forms\Components\Select::make('type')->options(Truck::TYPES)->required(),
                Forms\Components\TextInput::make('capacity_tonnes')->label('Capacity')->numeric()->minValue(1)->maxValue(60)->suffix('t')->required(),
                Forms\Components\Select::make('status')->options(TruckStatus::options())->required()->default('available'),
                Forms\Components\TextInput::make('make')->maxLength(60),
                Forms\Components\TextInput::make('model')->maxLength(60),
                Forms\Components\TextInput::make('year')->numeric()->minValue(1980)->maxValue((int) date('Y') + 1),
                Forms\Components\TextInput::make('tracker_device_id')->label('GPS tracker device ID')->unique(ignoreRecord: true)->maxLength(60)->helperText('Only if a tracker is fitted. Must match the ID the tracker company sends.'),
                Forms\Components\DatePicker::make('insurance_expires_on')->label('Insurance expires'),
                Forms\Components\DatePicker::make('roadworthiness_expires_on')->label('Roadworthiness expires'),
                Forms\Components\DatePicker::make('licence_expires_on')->label('Vehicle licence expires'),
                Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('registration')
            ->columns([
                Tables\Columns\TextColumn::make('registration')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('type')->formatStateUsing(fn (string $state): string => Truck::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('capacity_tonnes')->label('Capacity')->suffix(' t')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (TruckStatus $state): string => $state->label())->color(fn (TruckStatus $state): string => match ($state) {
                    TruckStatus::Available => 'success', TruckStatus::OnTrip => 'info', TruckStatus::Maintenance => 'warning', default => 'gray'
                }),
                Tables\Columns\TextColumn::make('driver.name')->label('Usual driver')->placeholder('—'),
                Tables\Columns\TextColumn::make('documents')->label('Documents')->state(fn (Truck $r): string => ($d = $r->expiringDocuments(30)) === [] ? 'OK' : implode(', ', array_map(fn ($x) => $x['document'].' '.($x['expires_on']->isPast() ? 'expired' : $x['expires_on']->diffForHumans()), $d)))->color(fn (Truck $r): ?string => $r->expiringDocuments(30) === [] ? null : 'danger')->wrap(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->options(TruckStatus::options()), Tables\Filters\SelectFilter::make('type')->options(Truck::TYPES)])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [TruckResource\RelationManagers\MaintenanceRecordsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrucks::route('/'),
            'create' => Pages\CreateTruck::route('/create'),
            'edit' => Pages\EditTruck::route('/{record}/edit'),
        ];
    }
}
