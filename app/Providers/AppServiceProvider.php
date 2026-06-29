<?php

namespace App\Providers;

use App\Auth\CaseInsensitiveUserProvider;
use App\EventForm\Template\LabelRenderer;
use App\Filament\Admin\Resources\ApplicationResource\Pages\ListApplications;
use App\Livewire\PersistTableStateHook;
use App\Models\Export;
use App\Models\Import;
use App\Services\Zgw\ZgwConnectionResolver;
use App\Support\CarbonBusinessDaysMixin;
use App\Support\Uploads\DocumentUploadType;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use Livewire\Livewire;

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

        // Use custom Export and Import models to fix user relationship caching issues
        $this->app->bind(
            \Filament\Actions\Exports\Models\Export::class,
            Export::class
        );
        $this->app->bind(
            \Filament\Actions\Imports\Models\Import::class,
            Import::class
        );

        // LabelRenderer cachet template-output per FormState-version.
        // Singleton zodat dezelfde instance gebruikt wordt over ALLE
        // schema-component-renders — anders zou de WeakMap-cache leeg
        // zijn bij elke Filament-resolution.
        $this->app->singleton(LabelRenderer::class);

        // Resolves the ZGW connection name per municipality. Singleton so the
        // per-municipality memo (and, later, runtime connection registration)
        // lives for the whole request/worker.
        $this->app->singleton(ZgwConnectionResolver::class);

        // Mirrors the session-persisted table state (filters, sort, search,
        // columns) into the database per user, so it survives a new session.
        // Must be registered before LivewireServiceProvider::boot() calls
        // ComponentHookRegistry::boot(), which only wires up hooks known at
        // that point - hence registering here rather than in boot().
        Livewire::componentHook(PersistTableStateHook::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            $expireMinutes = Config::get('auth.verification.expire', 60);
            $expiresAt = Carbon::now()->addMinutes($expireMinutes);

            return (new MailMessage)
                ->subject(Lang::get('Verify Email Address'))
                ->line(Lang::get('Please click the button below to verify your email address.'))
                ->line(Lang::get('This verification link is valid for :time (until :date). After this time, the link will expire and you will need to request a new verification.', [
                    'time' => CarbonInterval::minutes($expireMinutes)->cascade()->forHumans(),
                    'date' => $expiresAt->translatedFormat(config('app.datetime_format')),
                ]))
                ->action(Lang::get('Verify Email Address'), $url)
                ->line(Lang::get('If you did not create an account, no further action is required.'));
        });

        DocumentUploadType::assertConfigurationIsSafe(array_values((array) config('app.document_file_types', [])));

        Table::configureUsing(function (Table $table) {
            $table
                ->persistFiltersInSession()
                ->persistSortInSession()
                ->persistSearchInSession()
                ->persistColumnSearchesInSession()
                ->persistColumnsInSession();
        });

        // Register custom case-insensitive user provider
        Auth::provider('case-insensitive-eloquent', function ($app, array $config) {
            return new CaseInsensitiveUserProvider($app['hash'], $config['model']);
        });

        if (app()->isProduction()) {
            Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised());
        }
        Passport::tokensExpireIn(CarbonInterval::days(config('app.api.token_expire_in_days')));
        Passport::tokensCan([
            'api:access' => 'Algemene toegang tot de API endpoints',
            'notifications:receive' => 'Toegang tot de open-notifications listen webhook',
        ]);

        Carbon::mixin(new CarbonBusinessDaysMixin);

        // NotifySlackOfFailedJob and LogZgwRequest are registered automatically
        // by Laravel's event discovery (they live in app/Listeners and type-hint
        // their event). Registering them manually here as well would fire each
        // listener twice, so we rely on discovery only.
    }
}
