<?php

namespace App\Providers\Filament;

use App\Models\Advisory;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdvisorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('advisor')
            ->path('advisor')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->font('Satoshi', url: asset('css/fonts/satoshi.css'), provider: LocalFontProvider::class)
            ->brandLogo(asset('images/logos/logo-dark.svg'))
            ->darkModeBrandLogo(asset('images/logos/logo-light.svg'))
            ->brandLogoHeight('2.5rem')
            ->tenant(Advisory::class)
            ->discoverResources(in: app_path('Filament/Advisor/Resources'), for: 'App\\Filament\\Advisor\\Resources')
            ->discoverPages(in: app_path('Filament/Advisor/Pages'), for: 'App\\Filament\\Advisor\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->login()
            ->passwordReset()
            ->discoverWidgets(in: app_path('Filament/Advisor/Widgets'), for: 'App\\Filament\\Advisor\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
