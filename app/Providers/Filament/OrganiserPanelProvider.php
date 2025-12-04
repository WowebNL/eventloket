<?php

namespace App\Providers\Filament;

use App\Filament\Organiser\Clusters\Settings\Pages\EditOrganisationProfile;
use App\Filament\Organiser\Pages\Dashboard;
use App\Filament\Organiser\Pages\EditProfile;
use App\Filament\Organiser\Pages\Register;
use App\Filament\Organiser\Pages\Tenancy\RegisterOrganisation;
use App\Filament\Organiser\Widgets\Intro;
use App\Filament\Organiser\Widgets\Shortlink;
use App\Filament\Shared\Pages\Login;
use App\Models\Organisation;
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

class OrganiserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('organiser')
            ->path('organiser')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->font('Satoshi', url: asset('css/fonts/satoshi.css'), provider: LocalFontProvider::class)
            ->brandLogo(asset('images/logos/logo-dark.svg'))
            ->darkModeBrandLogo(asset('images/logos/logo-light.svg'))
            ->brandLogoHeight('2.5rem')
            ->viteTheme('resources/css/filament/organiser/theme.css')
            ->tenant(Organisation::class)
            ->discoverResources(in: app_path('Filament/Organiser/Resources'), for: 'App\\Filament\\Organiser\\Resources')
            ->discoverResources(in: app_path('Filament/Shared/Resources'), for: 'App\\Filament\\Shared\\Resources')
            ->discoverPages(in: app_path('Filament/Organiser/Pages'), for: 'App\\Filament\\Organiser\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->globalSearch(false)
            ->databaseNotifications()
            ->discoverClusters(in: app_path('Filament/Organiser/Clusters'), for: 'App\\Filament\\Organiser\\Clusters')
            ->login(Login::class)
            ->registration(Register::class)
            ->tenantRegistration(RegisterOrganisation::class)
            ->tenantProfile(EditOrganisationProfile::class)
            ->passwordReset()
            ->emailVerification()
            ->profile(EditProfile::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(),
            ], isRequired: config('app.require_2fa'))
            ->discoverWidgets(in: app_path('Filament/Organiser/Widgets'), for: 'App\\Filament\\Organiser\\Widgets')
            ->discoverWidgets(in: app_path('Filament/Shared'), for: 'App\Filament\Shared')
            ->widgets([
                Intro::class,
                Shortlink::class,
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
