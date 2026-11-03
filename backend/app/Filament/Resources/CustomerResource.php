<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Portal\PortalAccess;
use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?int $navigationSort = 40;

    protected static function viewPermission(): Permission
    {
        return Permission::CrmView;
    }

    protected static function managePermission(): ?Permission
    {
        return Permission::CrmManage;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(20),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('tin')->label('TIN')->maxLength(30),
                Forms\Components\TextInput::make('billing_address')->maxLength(255)->columnSpanFull(),
                Forms\Components\TextInput::make('payment_terms_days')->label('Payment terms')->numeric()->minValue(0)->maxValue(180)->suffix('days')->default(30),
                Forms\Components\Textarea::make('notes')->rows(3)->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('payment_terms_days')->label('Terms')->suffix(' days'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Customer portal (PRD 6): the contact gets an email to choose a password.
                Tables\Actions\Action::make('invitePortal')->label('Invite to portal')->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => auth()->user()?->can(Permission::CrmManage->value) ?? false)
                    ->form(fn (Customer $record): array => [
                        Forms\Components\TextInput::make('name')->label('Contact name')->required()->maxLength(120)->default($record->contact->name ?? $record->name),
                        Forms\Components\TextInput::make('email')->email()->required()->default($record->email),
                    ])
                    ->modalDescription('They will get an email with a link to choose a password, then see this customer\'s shipments, proof of delivery, invoices and quotes.')
                    ->action(function (Customer $record, array $data): void {
                        try {
                            app(PortalAccess::class)->invite($record, $data['name'], $data['email'], auth()->user());
                            Notification::make()->success()->title("Invitation sent to {$data['email']}.")->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
