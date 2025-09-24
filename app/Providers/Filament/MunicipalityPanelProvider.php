<?php

namespace App\Providers\Filament;

use App\Filament\Municipality\Pages\Dashboard;
use App\Filament\Shared\Pages\EditProfile;
use App\Models\Municipality;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MunicipalityPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('municipality')
            ->path('municipality')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->font('Satoshi', url: asset('css/fonts/satoshi.css'), provider: LocalFontProvider::class)
            ->brandLogo(asset('images/logos/logo-dark.svg'))
            ->darkModeBrandLogo(asset('images/logos/logo-light.svg'))
            ->brandLogoHeight('2.5rem')
            ->viteTheme('resources/css/filament/municipality/theme.css')
            ->tenant(Municipality::class)
            ->discoverClusters(in: app_path('Filament/Municipality/Clusters'), for: 'App\Filament\Municipality\Clusters')
            ->discoverResources(in: app_path('Filament/Municipality/Resources'), for: 'App\Filament\Municipality\Resources')
            ->discoverResources(in: app_path('Filament/Shared/Resources'), for: 'App\Filament\Shared\Resources')
            ->discoverPages(in: app_path('Filament/Municipality/Pages'), for: 'App\Filament\Municipality\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->login()
            ->passwordReset()
            ->profile(EditProfile::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(),
            ], isRequired: config('app.require_2fa'))
            ->discoverWidgets(in: app_path('Filament/Municipality/Widgets'), for: 'App\Filament\Municipality\Widgets')
            ->discoverWidgets(in: app_path('Filament/Shared'), for: 'App\Filament\Shared')
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
