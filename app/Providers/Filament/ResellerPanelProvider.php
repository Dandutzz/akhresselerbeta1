<?php

namespace App\Providers\Filament;

use App\Filament\Reseller\Pages\Auth\ResellerRegister;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel terpisah untuk reseller di `/reseller`. Login form sendiri,
 * resource sendiri (auto-scoped via ResellerScope di tiap model),
 * settings sendiri (ResellerSetting bukan SiteSetting global).
 */
class ResellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('reseller')
            ->path('reseller')
            ->login()
            ->registration(ResellerRegister::class)
            ->brandName('Reseller Studio')
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->topNavigation(false)
            ->discoverResources(in: app_path('Filament/Reseller/Resources'), for: 'App\\Filament\\Reseller\\Resources')
            ->discoverPages(in: app_path('Filament/Reseller/Pages'), for: 'App\\Filament\\Reseller\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Reseller/Widgets'), for: 'App\\Filament\\Reseller\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
