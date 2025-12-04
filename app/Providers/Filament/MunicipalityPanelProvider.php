<?php

namespace App\Providers\Filament;

use App\Filament\Municipality\Pages\Calendar;
use App\Filament\Municipality\Pages\Dashboard;
use App\Filament\Shared\Pages\EditProfile;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Models\Municipality;
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
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

use function Filament\Support\original_request;

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
            ->globalSearch(false)
            ->databaseNotifications()
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
            ])
            ->navigationItems([
                NavigationItem::make('newzaken')
                    ->label(__('resources/zaak.filters.workingstock.options.new'))
                    ->group(__('resources/zaak.plural_label'))
                    ->badge(fn (): int => Zaak::whereNull(['handled_status_set_by_user_id', 'reference_data->resultaat'])->count())
                    ->icon(Heroicon::InboxArrowDown)
                    ->url(fn (): string => ListZaken::getUrl(['filters' => ['workingstock' => ['workingstock' => 'new']]]))
                    ->isActiveWhen(fn (NavigationBuilder $builder): bool => original_request()->routeIs(ListZaken::getRouteName()) && original_request()->input('filters.workingstock.workingstock') == 'new'),
                NavigationItem::make('workingstock')
                    ->label(__('resources/zaak.filters.workingstock.options.me'))
                    ->group(__('resources/zaak.plural_label'))
                    ->badge(fn (): int => Zaak::where('handled_status_set_by_user_id', auth()->id())->whereNull('reference_data->resultaat')->count())
                    ->icon(Heroicon::Inbox)
                    ->url(fn (): string => ListZaken::getUrl(['filters' => ['workingstock' => ['workingstock' => 'me']]]))
                    ->isActiveWhen(fn (NavigationBuilder $builder): bool => original_request()->routeIs(ListZaken::getRouteName()) && original_request()->input('filters.workingstock.workingstock') == 'me'),
                NavigationItem::make('allzaken')
                    ->label(__('resources/zaak.filters.workingstock.options.all'))
                    ->group(__('resources/zaak.plural_label'))
                    ->icon(Heroicon::InboxStack)
                    ->url(fn (): string => ListZaken::getUrl(['filters' => ['workingstock' => ['workingstock' => 'all']]]))
                    ->isActiveWhen(fn (NavigationBuilder $builder): bool => original_request()->routeIs(ListZaken::getRouteName()) && original_request()->input('filters.workingstock.workingstock') == 'all'),
                NavigationItem::make('eventcalendar')
                    ->label(__('shared/widgets/calendar.navigation_label'))
                    ->group(__('shared/widgets/calendar.navigation_group_label'))
                    ->icon(Heroicon::CalendarDays)
                    ->url(fn (): string => Calendar::getUrl())
                    ->isActiveWhen(fn (NavigationBuilder $builder): bool => original_request()->routeIs(Calendar::getRouteName()) && (original_request()->input('viewtype') === null || original_request()->input('viewtype') == 'calendar')),
                NavigationItem::make('eventcalendarlist')
                    ->label(__('shared/widgets/calendar.navigation_label_list'))
                    ->group(__('shared/widgets/calendar.navigation_group_label'))
                    ->icon(Heroicon::TableCells)
                    ->url(fn (): string => Calendar::getUrl(['viewtype' => 'table']))
                    ->isActiveWhen(fn (NavigationBuilder $builder): bool => original_request()->routeIs(Calendar::getRouteName()) && original_request()->input('viewtype') == 'table'),
            ]);
    }
}
