<?php

namespace App\Providers\Filament;

use App\Filament\Advisor\Pages\Dashboard;
use App\Filament\Shared\Pages\EditProfile;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Models\Advisory;
use App\Models\Zaak;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

use function Filament\Support\original_request;

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
            ->viteTheme('resources/css/filament/advisor/theme.css')
            ->tenant(Advisory::class)
            ->discoverResources(in: app_path('Filament/Advisor/Resources'), for: 'App\\Filament\\Advisor\\Resources')
            ->discoverResources(in: app_path('Filament/Shared/Resources'), for: 'App\\Filament\\Shared\\Resources')
            ->discoverPages(in: app_path('Filament/Advisor/Pages'), for: 'App\\Filament\\Advisor\\Pages')
            ->discoverClusters(in: app_path('Filament/Advisor/Clusters'), for: 'App\\Filament\\Advisor\\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->databaseNotifications()
            ->login()
            ->passwordReset()
            ->profile(EditProfile::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(),
            ], isRequired: config('app.require_2fa'))
            ->discoverWidgets(in: app_path('Filament/Advisor/Widgets'), for: 'App\\Filament\\Advisor\\Widgets')
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
            ])
            ->navigationItems([
                NavigationItem::make('newzaken')
                    ->label(__('resources/zaak.filters.workingstock.options.new'))
                    ->group(__('resources/zaak.plural_label'))
                    ->badge(fn (): int => Zaak::whereHas('adviceThreads', fn (Builder $query) => $query->whereDoesntHave('assignedUsers'))->count())
                    ->icon(Heroicon::InboxArrowDown)
                    ->url(fn (): string => ListZaken::getUrl(['filters' => ['workingstock' => ['workingstock' => 'new']]]))
                    ->isActiveWhen(fn (NavigationBuilder $builder): bool => original_request()->routeIs(ListZaken::getRouteName()) && original_request()->input('filters.workingstock.workingstock') == 'new'),
                NavigationItem::make('workingstock')
                    ->label(__('resources/zaak.filters.workingstock.options.me'))
                    ->group(__('resources/zaak.plural_label'))
                    ->badge(fn (): int => Zaak::whereHas('adviceThreads.assignedUsers', fn (Builder $query) => $query->where('user_id', auth()->id()))->count())
                    ->icon(Heroicon::Inbox)
                    ->url(fn (): string => ListZaken::getUrl(['filters' => ['workingstock' => ['workingstock' => 'me']]]))
                    ->isActiveWhen(fn (NavigationBuilder $builder): bool => original_request()->routeIs(ListZaken::getRouteName()) && original_request()->input('filters.workingstock.workingstock') == 'me'),
                NavigationItem::make('allzaken')
                    ->label(__('resources/zaak.filters.workingstock.options.all'))
                    ->group(__('resources/zaak.plural_label'))
                    ->icon(Heroicon::InboxStack)
                    ->url(fn (): string => ListZaken::getUrl(['filters' => ['workingstock' => ['workingstock' => 'all']]]))
                    ->isActiveWhen(fn (NavigationBuilder $builder): bool => original_request()->routeIs(ListZaken::getRouteName()) && original_request()->input('filters.workingstock.workingstock') == 'all'),
            ]);
    }
}
