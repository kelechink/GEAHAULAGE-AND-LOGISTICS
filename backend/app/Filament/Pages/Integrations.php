<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\TrackingConnector;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

/**
 * Tracker connector settings and integration guide (PRD 8.4).
 *
 * @property Form $form
 */
class Integrations extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Integrations';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.integrations';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $newSecret = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::IntegrationsManage->value) ?? false;
    }

    public function mount(): void
    {
        $traccar = TrackingConnector::query()->firstOrCreate(['name' => 'traccar']);
        $webhook = TrackingConnector::query()->firstOrCreate(['name' => 'webhook']);

        $this->form->fill([
            'traccar_enabled' => $traccar->enabled,
            'traccar_url' => $traccar->settings['url'] ?? null,
            'traccar_token' => null,
            'webhook_enabled' => $webhook->enabled,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Traccar server')
                ->description('Polls a Traccar GPS server every minute. Map each tracker to a truck with the truck\'s "GPS tracker device ID" (the Traccar unique ID).')
                ->schema([
                    Forms\Components\Toggle::make('traccar_enabled')->label('Enabled'),
                    Forms\Components\TextInput::make('traccar_url')->label('Server URL')->url()->placeholder('https://traccar.example.com')
                        ->requiredIf('traccar_enabled', true),
                    Forms\Components\TextInput::make('traccar_token')->label('API token')->password()->revealable()
                        ->helperText('Leave blank to keep the saved token.'),
                ]),
            Forms\Components\Section::make('Generic webhook')
                ->description('For a local tracker company that pushes positions to GEA. Share the URL, the secret and docs/connector-integration.md with them.')
                ->schema([
                    Forms\Components\Toggle::make('webhook_enabled')->label('Enabled'),
                    Forms\Components\Placeholder::make('webhook_url')->label('Webhook URL')->content(fn (): string => route('api.connectors.webhook', 'webhook')),
                    Forms\Components\Placeholder::make('signature')->label('Signature header')
                        ->content('X-GEA-Signature: sha256=<HMAC-SHA256 of the raw request body, using the secret>'),
                ]),
        ]);
    }

    public function save(): void
    {
        $d = $this->form->getState();

        $traccar = TrackingConnector::query()->where('name', 'traccar')->firstOrFail();
        $settings = $traccar->settings ?? [];
        $settings['url'] = $d['traccar_url'] ?? null;
        if (filled($d['traccar_token'] ?? null)) {
            $settings['token'] = $d['traccar_token'];
        }
        $traccar->update(['enabled' => (bool) $d['traccar_enabled'], 'settings' => $settings]);

        $webhook = TrackingConnector::query()->where('name', 'webhook')->firstOrFail();
        $webhook->update(['enabled' => (bool) $d['webhook_enabled']]);
        if ($webhook->enabled && blank($webhook->secret)) {
            $this->rotate();
        }

        Notification::make()->title('Integrations saved')->success()->send();
    }

    public function rotate(): void
    {
        $secret = Str::random(48);
        TrackingConnector::query()->where('name', 'webhook')->firstOrFail()->update(['secret' => $secret]);
        $this->newSecret = $secret;
    }

    public function rotateSecretAction(): Action
    {
        return Action::make('rotateSecret')
            ->label('Create new webhook secret')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('The tracker company must switch to the new secret. Requests signed with the old one will be refused.')
            ->action(fn () => $this->rotate());
    }
}
