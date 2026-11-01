<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Settings\WebsiteSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

/**
 * The home page copy (PRD 7, porting rule 3). Lists of plain strings are edited as repeaters of {text}.
 */
class ManageWebsiteContent extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Website content';

    protected static ?string $title = 'Website content';

    protected static ?int $navigationSort = 1;

    protected static string $settings = WebsiteSettings::class;

    /** Settings stored as list<string>, shown as repeaters of ['text' => ...]. */
    private const STRING_LISTS = ['hero_lines', 'about_paragraphs', 'story_paragraphs', 'reasons', 'commitment_paragraphs'];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ContentManage->value) ?? false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (self::STRING_LISTS as $key) {
            $data[$key] = array_map(static fn ($t): array => ['text' => (string) $t], (array) ($data[$key] ?? []));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (self::STRING_LISTS as $key) {
            $data[$key] = array_values(array_filter(array_map(
                static fn ($row): string => trim((string) (is_array($row) ? ($row['text'] ?? '') : $row)),
                (array) ($data[$key] ?? []),
            )));
        }
        foreach (['values', 'steps', 'credentials'] as $key) {
            $data[$key] = array_values((array) ($data[$key] ?? []));
        }

        return $data;
    }

    private static function textList(string $name, string $label, bool $long = true): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make($name)
            ->label($label)
            ->simple($long
                ? Forms\Components\Textarea::make('text')->required()->rows(2)
                : Forms\Components\TextInput::make('text')->required())
            ->reorderable()
            ->minItems(1)
            ->defaultItems(1);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make()->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make('Hero and about')->schema([
                    self::textList('hero_lines', 'Headline lines', false)->maxItems(4),
                    Forms\Components\Textarea::make('hero_intro')->label('Intro under the headline')->required()->rows(2),
                    Forms\Components\TextInput::make('about_heading')->required(),
                    self::textList('about_paragraphs', 'About paragraphs'),
                    Forms\Components\Textarea::make('about_quote')->label('Highlighted statement')->required()->rows(2),
                ]),
                Forms\Components\Tabs\Tab::make('Story, mission, vision')->schema([
                    Forms\Components\Textarea::make('story_lead')->label('Story lead')->required()->rows(2),
                    self::textList('story_paragraphs', 'Story paragraphs'),
                    Forms\Components\Textarea::make('mission')->required()->rows(3),
                    Forms\Components\Textarea::make('vision')->required()->rows(3),
                ]),
                Forms\Components\Tabs\Tab::make('Values and reasons')->schema([
                    Forms\Components\Textarea::make('services_intro')->label('Services intro')->required()->rows(2),
                    Forms\Components\Repeater::make('values')->label('Core values')->schema([
                        Forms\Components\TextInput::make('title')->required(),
                        Forms\Components\Textarea::make('text')->required()->rows(2),
                    ])->columns(2)->reorderable()->minItems(1),
                    self::textList('reasons', 'Why choose GEA', false),
                    Forms\Components\Textarea::make('reasons_closing')->label('Closing line')->required()->rows(2),
                    Forms\Components\Textarea::make('commitment_statement')->required()->rows(2),
                    self::textList('commitment_paragraphs', 'Commitment paragraphs'),
                ]),
                Forms\Components\Tabs\Tab::make('Steps, careers, quote')->schema([
                    Forms\Components\Repeater::make('steps')->label('How it works')->schema([
                        Forms\Components\TextInput::make('title')->required(),
                        Forms\Components\Textarea::make('text')->required()->rows(2),
                    ])->columns(2)->reorderable()->minItems(1),
                    Forms\Components\Textarea::make('careers_intro')->required()->rows(2),
                    Forms\Components\Textarea::make('quote_intro')->label('Quote section intro')->required()->rows(3),
                ]),
                Forms\Components\Tabs\Tab::make('Credentials and emails')->schema([
                    Forms\Components\Repeater::make('credentials')
                        ->helperText('Add a credential only when GEA has given proof of it (PRD 7).')
                        ->schema([
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\TextInput::make('detail')->required(),
                        ])->columns(2)->reorderable(),
                    Forms\Components\Toggle::make('show_clients')->label('Show the clients section')
                        ->helperText('Keep off until GEA sends client logos with permission to show them.'),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('email_info')->label('General enquiries')->email()->required(),
                        Forms\Components\TextInput::make('email_sales')->label('Quotes and new business')->email()->required()
                            ->helperText('New quote requests are emailed here.'),
                        Forms\Components\TextInput::make('email_operations')->label('Bookings and operations')->email()->required(),
                        Forms\Components\TextInput::make('email_support')->label('Tracking and delivery support')->email()->required(),
                        Forms\Components\TextInput::make('email_billing')->label('Invoices and payments')->email()->required(),
                    ]),
                ]),
            ]),
        ]);
    }
}
