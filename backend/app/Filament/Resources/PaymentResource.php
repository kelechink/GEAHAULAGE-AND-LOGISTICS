<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Finance\Payment;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?int $navigationSort = 30;

    protected static function viewPermission(): Permission
    {
        return Permission::FinanceView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::FinanceManage;
    }

    /** Payments are recorded through the service and never edited afterwards; mistakes are reversed with a credit note. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
                Forms\Components\DatePicker::make('received_on')->default(now())->required(),
                MoneyInput::make('amount_kobo', 'Amount received')->required(),
                MoneyInput::make('wht_kobo', 'WHT deducted by customer')->default('0'),
                Forms\Components\Select::make('method')->options(PaymentMethod::options())->default('bank_transfer')->required(),
                Forms\Components\TextInput::make('reference')->maxLength(80),
                Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull()
                    ->helperText('The payment is applied to the customer\'s oldest unpaid invoices first. Anything left over stays as credit.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_on', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'allocations.invoice']))
            ->columns([
                Tables\Columns\TextColumn::make('received_on')->date('j M Y')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('amount_kobo')->label('Amount')->formatStateUsing(fn (int $state): string => Money::kobo($state)->format())->alignEnd(),
                Tables\Columns\TextColumn::make('wht_kobo')->label('WHT')->formatStateUsing(fn (int $state): string => Money::kobo($state)->format())->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('method')->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                Tables\Columns\TextColumn::make('reference')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('invoices')->label('Applied to')
                    ->state(fn (Payment $r): string => $r->allocations->map(fn ($a) => $a->invoice->displayNumber())->implode(', ') ?: 'Customer credit'),
                Tables\Columns\TextColumn::make('unallocated_kobo')->label('Credit')->formatStateUsing(fn (int $state): string => $state > 0 ? Money::kobo($state)->format() : '—')->alignEnd(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('method')->options(PaymentMethod::options())]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
        ];
    }
}
