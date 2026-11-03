<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ExpenseCategory;
use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Finance\Expense;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?int $navigationSort = 40;

    protected static function viewPermission(): Permission
    {
        return Permission::FinanceView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::FinanceManage;
    }

    /** Maintenance expenses are edited from the truck's maintenance log so the two stay in step. */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof Expense && $record->maintenance_record_id === null && (auth()->user()?->can(Permission::FinanceManage->value) ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('category')->options(ExpenseCategory::options())->required(),
                Forms\Components\DatePicker::make('spent_on')->default(now())->required(),
                MoneyInput::make('amount_kobo', 'Amount')->required(),
                Forms\Components\TextInput::make('vendor')->maxLength(120),
                Forms\Components\TextInput::make('description')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\Select::make('truck_id')->label('Truck')->relationship('truck', 'registration')->searchable()->preload(),
                Forms\Components\Select::make('shipment_id')->label('Trip')->relationship('shipment', 'tracking_code')->searchable(),
                Forms\Components\FileUpload::make('receipt_path')->label('Receipt')->disk('local')->directory('receipts')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])->maxSize(8192)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('spent_on', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['truck', 'shipment']))
            ->columns([
                Tables\Columns\TextColumn::make('spent_on')->date('j M Y')->sortable(),
                Tables\Columns\TextColumn::make('category')->badge()->formatStateUsing(fn (ExpenseCategory $state): string => $state->label()),
                Tables\Columns\TextColumn::make('description')->searchable()->limit(36),
                Tables\Columns\TextColumn::make('truck.registration')->label('Truck')->placeholder('—'),
                Tables\Columns\TextColumn::make('shipment.tracking_code')->label('Trip')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('amount_kobo')->label('Amount')->formatStateUsing(fn (int $state): string => Money::kobo($state)->format())->alignEnd()->sortable(),
                Tables\Columns\IconColumn::make('receipt_path')->label('Receipt')->boolean(fn ($state): bool => filled($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')->options(ExpenseCategory::options()),
                Tables\Filters\SelectFilter::make('truck_id')->label('Truck')->relationship('truck', 'registration'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
