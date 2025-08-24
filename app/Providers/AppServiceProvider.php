<?php

namespace App\Providers;

use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use Carbon\CarbonInterval;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

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
    }
}
