<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Documents\DocumentLinks;
use App\Domain\Finance\AmountInWords;
use App\Domain\Finance\Documents\DocumentRenderer;
use App\Domain\Finance\FinanceException;
use App\Domain\Finance\InvoiceService;
use App\Domain\Finance\PaymentService;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceTemplate;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Filament\Concerns\DocumentFormParts;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Mail\InvoiceMail;
use App\Models\Finance\Invoice;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    protected static function viewPermission(): Permission
    {
        return Permission::FinanceView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::FinanceManage;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Invoice && $record->status === InvoiceStatus::Draft && (auth()->user()?->can(Permission::FinanceManage->value) ?? false);
    }

    public static function userCan(string $ability): bool
    {
        return auth()->user()?->can(match ($ability) {
            'manage' => Permission::FinanceManage->value,
            'void' => Permission::InvoicesApproveVoid->value,
            default => Permission::FinanceView->value,
        }) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required()->columnSpan(2),
                Forms\Components\Select::make('template')->options(InvoiceTemplate::invoiceOptions())->default(InvoiceTemplate::HaulageTrip->value)->required(),
                Forms\Components\Select::make('shipment_id')->label('Trip')->relationship('shipment', 'tracking_code')->searchable()
                    ->helperText('Links the trip details onto the invoice.'),
                Forms\Components\DatePicker::make('due_date')->label('Due date')->helperText('Leave empty to use the customer\'s payment terms.'),
                DocumentFormParts::vatRate(),
            ]),
            Forms\Components\Section::make()->schema([
                DocumentFormParts::lines(),
                Forms\Components\Grid::make(3)->schema([
                    MoneyInput::make('discount_kobo', 'Discount')->default('0')->live(onBlur: true),
                    DocumentFormParts::totals()->columnSpan(2),
                ]),
                Forms\Components\Textarea::make('notes')->rows(2)->helperText('Printed on the invoice.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('number')->placeholder('Draft')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('customer.name')->searchable()->limit(28),
                Tables\Columns\TextColumn::make('issue_date')->date('j M Y')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('due_date')->date('j M Y')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('total_kobo')->label('Total')->formatStateUsing(fn (int $state): string => Money::kobo($state)->format())->alignEnd()->sortable(),
                Tables\Columns\TextColumn::make('balance_kobo')->label('Balance')->formatStateUsing(fn (int $state): string => Money::kobo($state)->format())->alignEnd()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (InvoiceStatus $state): string => $state->label())
                    ->color(fn (InvoiceStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(InvoiceStatus::options())->multiple(),
                Tables\Filters\Filter::make('unpaid')->label('Unpaid')->query(fn (Builder $query) => $query->whereIn('status', ['issued', 'partially_paid', 'overdue'])),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->bulkActions([
                Tables\Actions\BulkAction::make('zipPdf')->label('Download PDFs (ZIP)')->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Collection $records) => self::zipDownload($records, 'pdf')),
                Tables\Actions\BulkAction::make('zipDocx')->label('Download Word files (ZIP)')->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Collection $records) => self::zipDownload($records, 'docx')),
            ]);
    }

    /** @param Collection<int, Invoice> $records */
    public static function zipDownload(Collection $records, string $format): StreamedResponse
    {
        $bytes = app(DocumentRenderer::class)->zip($records, $format);

        return response()->streamDownload(fn () => print ($bytes), 'gea-invoices-'.now()->format('Ymd-His').".{$format}.zip", ['Content-Type' => 'application/zip']);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $money = fn (?int $state): string => Money::kobo((int) $state)->format();

        return $infolist->schema([
            Infolists\Components\Section::make()->columns(4)->schema([
                Infolists\Components\TextEntry::make('number')->placeholder('Draft (numbered when issued)')->weight('bold'),
                Infolists\Components\TextEntry::make('status')->badge()->formatStateUsing(fn (InvoiceStatus $state): string => $state->label())->color(fn (InvoiceStatus $state): string => $state->color()),
                Infolists\Components\TextEntry::make('customer.name'),
                Infolists\Components\TextEntry::make('template')->formatStateUsing(fn (InvoiceTemplate $state): string => $state->label()),
                Infolists\Components\TextEntry::make('issue_date')->date('j M Y')->placeholder('Not issued'),
                Infolists\Components\TextEntry::make('due_date')->date('j M Y')->placeholder('On issue'),
                Infolists\Components\TextEntry::make('shipment.tracking_code')->label('Trip')->placeholder('—'),
                Infolists\Components\TextEntry::make('irn')->label('E-invoice IRN')->placeholder('—'),
            ]),
            Infolists\Components\Section::make('Lines')->schema([
                Infolists\Components\RepeatableEntry::make('lines')->label('')->columns(4)->schema([
                    Infolists\Components\TextEntry::make('description')->columnSpan(2),
                    Infolists\Components\TextEntry::make('quantity')->formatStateUsing(fn ($state): string => rtrim(rtrim((string) $state, '0'), '.')),
                    Infolists\Components\TextEntry::make('amount_kobo')->label('Amount')->formatStateUsing($money),
                ]),
            ]),
            Infolists\Components\Section::make('Totals')->columns(4)->schema([
                Infolists\Components\TextEntry::make('subtotal_kobo')->label('Subtotal')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('discount_kobo')->label('Discount')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('vat_kobo')->label('VAT')->formatStateUsing($money)
                    ->helperText(fn (Invoice $r): string => rtrim(rtrim(number_format($r->vat_rate_bp / 100, 2), '0'), '.').'%'),
                Infolists\Components\TextEntry::make('total_kobo')->label('Total')->formatStateUsing($money)->weight('bold'),
                Infolists\Components\TextEntry::make('amount_paid_kobo')->label('Paid')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('wht_kobo')->label('WHT deducted')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('credited_kobo')->label('Credit notes')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('balance_kobo')->label('Balance due')->formatStateUsing($money)->weight('bold')->color('primary'),
                Infolists\Components\TextEntry::make('words')->label('In words')->columnSpanFull()
                    ->state(fn (Invoice $r): string => AmountInWords::naira($r->total_kobo)),
                Infolists\Components\TextEntry::make('void_reason')->visible(fn (Invoice $r): bool => $r->status === InvoiceStatus::Void)->color('danger')->columnSpanFull(),
            ]),
        ]);
    }

    /** @return list<Action|ActionGroup> */
    public static function viewActions(Invoice $invoice): array
    {
        $renderer = app(DocumentRenderer::class);
        // Filament fills action parameters by name, so the wrapper must declare $data explicitly.
        $notify = fn (callable $fn, string $ok) => function (array $data = []) use ($fn, $ok): void {
            try {
                $fn($data);
                Notification::make()->title($ok)->success()->send();
            } catch (FinanceException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
            }
        };

        return [
            Action::make('issue')->label('Issue invoice')->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => $invoice->status === InvoiceStatus::Draft && self::userCan('manage'))
                ->requiresConfirmation()->modalDescription('Issuing gives the invoice its number. It can no longer be edited after this.')
                ->form([Forms\Components\Toggle::make('email')->label('Email it to the customer now')->default(fn (): bool => filled($invoice->customer->email))])
                ->action($notify(function (array $data) use ($invoice): void {
                    app(InvoiceService::class)->issue($invoice, auth()->user());
                    if (($data['email'] ?? false) && filled($invoice->customer->email)) {
                        Mail::to($invoice->customer->email)->queue(new InvoiceMail($invoice->refresh()));
                    }
                }, 'Invoice issued')),
            Action::make('payment')->label('Record payment')->icon('heroicon-o-banknotes')
                ->visible(fn (): bool => $invoice->status->isReceivable() && self::userCan('manage'))
                ->form([
                    MoneyInput::make('amount_kobo', 'Amount received')->required()->default(fn (): string => Money::kobo($invoice->balance_kobo)->toDecimalString()),
                    MoneyInput::make('wht_kobo', 'WHT deducted by customer')->default('0'),
                    Forms\Components\Select::make('method')->options(PaymentMethod::options())->default('bank_transfer')->required(),
                    Forms\Components\DatePicker::make('received_on')->default(now())->required(),
                    TextInput::make('reference')->maxLength(80),
                ])
                ->action($notify(function (array $data) use ($invoice): void {
                    app(PaymentService::class)->record($invoice->customer, (int) $data['amount_kobo'], PaymentMethod::from($data['method']),
                        Carbon::parse($data['received_on']), $data['reference'] ?? null, (int) $data['wht_kobo'],
                        [['invoice_id' => $invoice->id, 'amount_kobo' => (int) $data['amount_kobo'], 'wht_kobo' => (int) $data['wht_kobo']]], auth()->user());
                }, 'Payment recorded')),
            Action::make('credit')->label('Credit note')->icon('heroicon-o-receipt-refund')->color('gray')
                ->visible(fn (): bool => ! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true) && self::userCan('manage'))
                ->form([MoneyInput::make('amount_kobo', 'Amount')->required(), TextInput::make('reason')->required()->maxLength(255)])
                ->action($notify(fn (array $data) => app(InvoiceService::class)->creditNote($invoice, (int) $data['amount_kobo'], $data['reason'], auth()->user()), 'Credit note raised')),
            Action::make('void')->label('Void')->icon('heroicon-o-no-symbol')->color('danger')
                ->visible(fn (): bool => $invoice->status !== InvoiceStatus::Void && self::userCan('void'))
                ->form([TextInput::make('reason')->label('Why is it being voided?')->required()->maxLength(255)])
                ->action($notify(fn (array $data) => app(InvoiceService::class)->void($invoice, $data['reason'], auth()->user()), 'Invoice voided')),
            ActionGroup::make([
                Action::make('pdf')->label('Download PDF')->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => response()->streamDownload(fn () => print ($renderer->pdf($invoice)), $renderer->filename($invoice, 'pdf'), ['Content-Type' => 'application/pdf'])),
                Action::make('docx')->label('Download Word')->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => response()->streamDownload(fn () => print ($renderer->docx($invoice)), $renderer->filename($invoice, 'docx'),
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])),
                Action::make('link')->label('Customer link (30 days)')->icon('heroicon-o-link')
                    ->visible(fn (): bool => $invoice->status !== InvoiceStatus::Draft)
                    ->modalHeading('Link to this invoice')
                    ->modalDescription('Anyone with this link can open the PDF for 30 days. Paste it into WhatsApp or an email.')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->form([TextInput::make('url')->label('Link')->default(fn (): string => DocumentLinks::invoice($invoice))->readOnly()->extraInputAttributes(['onclick' => 'this.select()'])]),
                Action::make('email')->label('Email to customer')->icon('heroicon-o-envelope')
                    ->visible(fn (): bool => $invoice->status !== InvoiceStatus::Draft && filled($invoice->customer->email) && self::userCan('manage'))
                    ->requiresConfirmation()
                    ->action(function () use ($invoice): void {
                        Mail::to((string) $invoice->customer->email)->queue(new InvoiceMail($invoice));
                        Notification::make()->title('Invoice emailed to '.$invoice->customer->email)->success()->send();
                    }),
                Action::make('whatsapp')->label('WhatsApp message')->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn (): bool => $invoice->status !== InvoiceStatus::Draft && filled($invoice->customer->phone))
                    ->url(fn (): string => 'https://wa.me/'.ltrim((string) $invoice->customer->phone, '+').'?text='.rawurlencode(
                        "Hello {$invoice->customer->name}, invoice {$invoice->displayNumber()} from GEA Haulage and Logistics: total ".Money::kobo($invoice->total_kobo)->format()
                        .', balance '.Money::kobo($invoice->balance_kobo)->format().', due '.($invoice->due_date?->format('j M Y') ?? '').'. We have emailed you the PDF. Thank you.'
                    ), shouldOpenInNewTab: true),
            ])->label('Documents')->icon('heroicon-o-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
