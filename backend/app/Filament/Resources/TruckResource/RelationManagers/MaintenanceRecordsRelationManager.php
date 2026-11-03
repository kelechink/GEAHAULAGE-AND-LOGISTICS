<?php

declare(strict_types=1);

namespace App\Filament\Resources\TruckResource\RelationManagers;

use App\Enums\Permission;
use App\Models\MaintenanceRecord;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Maintenance history. From Phase 5 each cost also posts to Expenses (PRD 10.2).
 */
class MaintenanceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceRecords';

    protected static ?string $title = 'Maintenance';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can(Permission::FleetManage->value) ?? false);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('done_on')->label('Date')->required()->default(now()),
            Forms\Components\TextInput::make('description')->required()->maxLength(255),
            Forms\Components\TextInput::make('cost')->label('Cost')->prefix('₦')->required()
                ->regex('/^\d{1,3}(,?\d{3})*(\.\d{1,2})?$/')
                ->formatStateUsing(fn (?MaintenanceRecord $record) => $record !== null ? Money::kobo($record->cost_kobo)->toDecimalString() : null)
                ->dehydrateStateUsing(fn (?string $state): int => Money::naira((string) $state)->kobo)
                ->dehydrated(true)
                ->statePath('cost_kobo'),
            Forms\Components\TextInput::make('workshop')->maxLength(120),
            Forms\Components\TextInput::make('odometer_km')->label('Odometer')->numeric()->suffix('km'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('done_on', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('done_on')->label('Date')->date('j M Y'),
                Tables\Columns\TextColumn::make('description')->wrap(),
                Tables\Columns\TextColumn::make('cost_kobo')->label('Cost')->formatStateUsing(fn (int $state): string => Money::kobo($state)->format()),
                Tables\Columns\TextColumn::make('workshop')->placeholder('—'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Log maintenance')])
            ->actions([Tables\Actions\EditAction::make()]);
    }
}
