<?php

namespace App\Providers;

use App\Actions\OpenNotification\GetIncommingNotificationType;
use App\Auth\CaseInsensitiveUserProvider;
use App\Console\Commands\SyncZaaktypen;
use App\Filament\Admin\Resources\ApplicationResource\Pages\ListApplications;
use App\Jobs\ProcessOpenNotification;
use App\Jobs\Zaak\AddEinddatumZGW;
use App\Jobs\Zaak\AddGeometryZGW;
use App\Jobs\Zaak\AddZaakeigenschappenZGW;
use App\Jobs\Zaak\CreateZaak;
use App\Jobs\Zaak\UpdateInitiatorZGW;
use App\Support\CarbonBusinessDaysMixin;
use App\Support\Uploads\DocumentUploadType;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Filament\Support\Facades\FilamentView;
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

        // Use custom Export and Import models to fix user relationship caching issues
        $this->app->bind(
            \Filament\Actions\Exports\Models\Export::class,
            \App\Models\Export::class
        );
        $this->app->bind(
            \Filament\Actions\Imports\Models\Import::class,
            \App\Models\Import::class
        );
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

        // Register custom case-insensitive user provider
        Auth::provider('case-insensitive-eloquent', function ($app, array $config) {
            return new CaseInsensitiveUserProvider($app['hash'], $config['model']);
        });

        if (app()->isProduction()) {
            Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised());
        }
        Passport::tokensExpireIn(CarbonInterval::days(config('app.api.token_expire_in_days')));

        Carbon::mixin(new CarbonBusinessDaysMixin);

        $this->bindCustomMethods();
    }

    private function bindCustomMethods(): void
    {
        $this->app->bindMethod([ProcessOpenNotification::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class), typeProcessor: app(GetIncommingNotificationType::class)));
        $this->app->bindMethod([AddZaakeigenschappenZGW::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class), objectsapi: app(ObjectsApi::class)));
        $this->app->bindMethod([UpdateInitiatorZGW::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class), objectsapi: app(ObjectsApi::class)));
        $this->app->bindMethod([CreateZaak::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class), objectsapi: app(ObjectsApi::class)));
        $this->app->bindMethod([AddGeometryZGW::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class), objectsapi: app(ObjectsApi::class)));
        $this->app->bindMethod([AddEinddatumZGW::class, 'handle'], fn ($job) => $job->handle(openzaak: app(Openzaak::class)));

        $this->app->bindMethod([SyncZaaktypen::class, 'handle'], fn ($command) => $command->handle(app(Openzaak::class)));
    }
}
