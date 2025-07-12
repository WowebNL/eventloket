<?php

namespace App\Providers\Filament;

use App\Filament\Organiser\Clusters\Settings\Pages\EditOrganisationProfile;
use App\Filament\Organiser\Pages\Register;
use App\Filament\Organiser\Pages\Tenancy\RegisterOrganisation;
use App\Models\Organisation;
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
            ->tenant(Organisation::class)
            ->discoverResources(in: app_path('Filament/Organiser/Resources'), for: 'App\\Filament\\Organiser\\Resources')
            ->discoverPages(in: app_path('Filament/Organiser/Pages'), for: 'App\\Filament\\Organiser\\Pages')
            ->discoverClusters(in: app_path('Filament/Organiser/Clusters'), for: 'App\\Filament\\Organiser\\Clusters')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->login()
            ->registration(Register::class)
            ->tenantRegistration(RegisterOrganisation::class)
            ->tenantProfile(EditOrganisationProfile::class)
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->discoverWidgets(in: app_path('Filament/Organiser/Widgets'), for: 'App\\Filament\\Organiser\\Widgets')
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
