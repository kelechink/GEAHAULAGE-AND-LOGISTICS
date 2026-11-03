<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Finance\Documents\DocumentRenderer;
use App\Domain\Finance\FinanceException;
use App\Domain\Finance\QuoteService;
use App\Enums\Permission;
use App\Enums\QuoteStatus;
use App\Filament\Concerns\DocumentFormParts;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\QuoteResource\Pages;
use App\Models\Finance\Quote;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QuoteResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Quote::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Quotes and proformas';

    protected static ?string $modelLabel = 'quote';

    protected static ?int $navigationSort = 10;

    /** Sales prepare quotes; Finance can see them (PRD 4). */
    protected static function viewPermission(): Permission
    {
        return Permission::FinanceView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::QuotesManage;
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->can(Permission::QuotesManage->value) || $u->can(Permission::FinanceView->value));
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Quote && $record->status !== QuoteStatus::Converted && (auth()->user()?->can(Permission::QuotesManage->value) ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required()->columnSpan(2)
                    ->createOptionForm([Forms\Components\TextInput::make('name')->required(), Forms\Components\TextInput::make('email')->email(), Forms\Components\TextInput::make('phone')->tel()]),
                Forms\Components\Select::make('lead_id')->label('CRM lead')->relationship('lead', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => '#'.$record->id.' '.($record->contact->name ?? '').' · '.($record->origin ?? '?').' → '.($record->destination ?? '?'))
                    ->searchable()->preload(),
                DocumentFormParts::vatRate(),
                Forms\Components\DatePicker::make('valid_until')->helperText('Leave empty for the default validity.'),
            ]),
            Forms\Components\Section::make()->schema([
                DocumentFormParts::lines(),
                Forms\Components\Grid::make(3)->schema([
                    MoneyInput::make('discount_kobo', 'Discount')->default('0')->live(onBlur: true),
                    DocumentFormParts::totals()->columnSpan(2),
                ]),
                Forms\Components\Textarea::make('notes')->rows(2),
                Forms\Components\Textarea::make('terms')->rows(2)->placeholder('Prices are valid until the date shown and depend on truck availability.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('issue_date')->date('j M Y'),
                Tables\Columns\TextColumn::make('valid_until')->date('j M Y')->color(fn (Quote $r): ?string => $r->valid_until->isPast() && $r->status !== QuoteStatus::Converted ? 'danger' : null),
                Tables\Columns\TextColumn::make('total_kobo')->label('Total')->formatStateUsing(fn (int $state): string => Money::kobo($state)->format())->alignEnd(),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (QuoteStatus $state): string => $state->label()),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->options(QuoteStatus::options())])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')
                    ->action(fn (Quote $record) => response()->streamDownload(fn () => print (app(DocumentRenderer::class)->pdf($record)), app(DocumentRenderer::class)->filename($record, 'pdf'))),
                Tables\Actions\Action::make('docx')->label('Word')->icon('heroicon-o-document-arrow-down')
                    ->action(fn (Quote $record) => response()->streamDownload(fn () => print (app(DocumentRenderer::class)->docx($record)), app(DocumentRenderer::class)->filename($record, 'docx'))),
                Tables\Actions\Action::make('send')->label('Mark sent')->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Quote $r): bool => $r->status === QuoteStatus::Draft && (auth()->user()?->can(Permission::QuotesManage->value) ?? false))
                    ->action(function (Quote $record): void {
                        app(QuoteService::class)->markSent($record, auth()->user());
                        Notification::make()->title('Quote marked as sent. The lead is now "Quoted".')->success()->send();
                    }),
                Tables\Actions\Action::make('convert')->label('Turn into invoice')->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn (Quote $r): bool => in_array($r->status, [QuoteStatus::Sent, QuoteStatus::Accepted, QuoteStatus::Draft], true)
                        && (auth()->user()?->can(Permission::FinanceManage->value) ?? false))
                    ->requiresConfirmation()
                    ->action(function (Quote $record) {
                        try {
                            $invoice = app(QuoteService::class)->convertToInvoice($record, auth()->user());

                            return redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                        } catch (FinanceException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }

                        return null;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}
