<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\TwoFactorSetup;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\StaffLoginOnly;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => Blade::render('<div class="gea-topbar-theme" style="margin-inline-end:.75rem"><x-filament-panels::theme-switcher /></div>'))
            ->brandName('GEA Haulage and Logistics')
            ->brandLogo(asset('assets/img/logo-blue.png'))
            ->darkModeBrandLogo(asset('assets/img/logo-white.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('assets/img/favicon.png'))
            ->colors([
                // GEA navy from the logo, with the website's gold as the warning colour.
                'primary' => Color::hex('#172F66'),
                'warning' => Color::hex('#F5C400'),
                'danger' => Color::hex('#C8102E'),
                'gray' => Color::Slate,
            ])
            ->font('Barlow')
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<link rel="stylesheet" href="'.asset('css/admin.css').'?v='.filemtime(public_path('css/admin.css')).'">')
            ->navigationGroups([
                NavigationGroup::make('CRM'),
                NavigationGroup::make('Operations'),
                NavigationGroup::make('Fleet'),
                NavigationGroup::make('Finance'),
                NavigationGroup::make('Assistant'),
                NavigationGroup::make('Content'),
                NavigationGroup::make('Settings')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                StaffLoginOnly::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureTwoFactor::class,
            ])
            ->userMenuItems([
                MenuItem::make()->label('Two-factor sign-in')->icon('heroicon-o-shield-check')->url(fn (): string => TwoFactorSetup::getUrl()),
            ]);
    }
}
