<?php

namespace App\Providers;

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Console\Commands\SyncZaaktypen;
use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Jobs\ProcessOpenNotification;
use App\Jobs\Zaak\AddZaakeigenschappenZGW;
use App\Jobs\Zaak\CreateZaak;
use Carbon\CarbonInterval;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
            fn (): View => view('filament.components.resource-information'),
            scopes: [
                ListApplications::class,
            ]
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised());
        }
        Passport::tokensExpireIn(CarbonInterval::days(config('app.api.token_expire_in_days')));

        $this->bindCustomMethods();
    }

    private function bindCustomMethods(): void
    {
        $this->app->bindMethod([ProcessOpenNotification::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class), typeProcessor: app(GetIncommingNotificationType::class)));
        $this->app->bindMethod([AddZaakeigenschappenZGW::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class), objectsapi: app(ObjectsApi::class)));
        $this->app->bindMethod([CreateZaak::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class)));

        $this->app->bindMethod([SyncZaaktypen::class, 'handle'], fn ($command) => $command->handle(app(Openzaak::class)));
    }
}
