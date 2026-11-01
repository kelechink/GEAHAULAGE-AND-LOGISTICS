<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Settings\CompanySettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageCompanySettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Company and tax';

    protected static ?string $title = 'Company and tax';

    protected static ?int $navigationSort = 1;

    protected static string $settings = CompanySettings::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Company')->columns(2)->schema([
                Forms\Components\TextInput::make('company_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('rc_number')->label('CAC RC number')->required()->maxLength(20),
                Forms\Components\Textarea::make('address')->required()->rows(2)->columnSpanFull(),
                Forms\Components\TextInput::make('phone')->tel()->required(),
                Forms\Components\TextInput::make('whatsapp')
                    ->label('WhatsApp number')
                    ->helperText('International format without +, e.g. 2348162163235')
                    ->regex('/^234\d{10}$/')
                    ->required(),
                Forms\Components\TextInput::make('email')->email()->required(),
            ]),
            Forms\Components\Section::make('Tax')->columns(3)->schema([
                Forms\Components\TextInput::make('tin')->label('TIN')->maxLength(20),
                Forms\Components\TextInput::make('vat_number')->label('VAT number')->maxLength(20),
                Forms\Components\TextInput::make('vat_rate')
                    ->label('Default VAT rate')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->suffix('%')
                    ->required(),
            ]),
            Forms\Components\Section::make('Website')->schema([
                Forms\Components\Toggle::make('show_cross_border')
                    ->label('Show West Africa and cross-border coverage')
                    ->helperText('Keep off until GEA confirms cross-border operations (PRD open question 1).'),
            ]),
        ]);
    }
}
