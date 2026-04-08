<?php

namespace App\Filament\Organiser\Pages;

use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\Services\EventloketTokenService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;

class NewRequest extends Page
{
    #[Locked]
    public $formId;

    #[Locked]
    public $eventloketToken;

    protected static ?string $slug = 'new-request/{eventloketToken?}/{openform?}';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.organiser.pages.new-request';

    public static function routes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        Route::get(static::getRoutePath($panel), static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName($panel))
            ->where('openform', '.*');
    }

    public function mount(?string $eventloketToken = null): void
    {
        $this->formId = config('services.open_forms.main_form_uuid');

        if ($eventloketToken) {
            $this->eventloketToken = $eventloketToken;
        } else {
            $this->eventloketToken = $this->generateToken();
            $this->redirect($this->getUrlWithToken());
        }
    }

    public function checkInitialLoad()
    {
        $this->js('loadForm();');
    }

    public function getTitle(): string
    {
        return '';
    }

    public function formSaved(): void
    {
        Notification::make()
            ->title(__('Formulier succesvol opgeslagen'))
            ->body(__('Het formulier is succesvol opgeslagen. Om het formulier opnieuw te openen dien je op de link uit de mail te klikken.'))
            ->success()
            ->send()
            ->persistent();
    }

    public static function getNavigationLabel(): string
    {
        return __('organiser/pages/new-request.navigation_label');
    }

    public static function getNavigationUrl(): string
    {
        // Navigate without token — mount() will generate one and redirect
        return route('filament.organiser.pages.new-request.{eventloketToken?}.{openform?}', [
            'tenant' => Filament::getTenant(),
        ]);
    }

    private function generateToken(): string
    {
        /** @var OrganiserUser $user */
        $user = Filament::auth()->user();
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();

        return app(EventloketTokenService::class)->generate($user->uuid, $tenant->uuid);
    }

    private function getUrlWithToken(): string
    {
        return route('filament.organiser.pages.new-request.{eventloketToken?}.{openform?}', [
            'tenant' => Filament::getTenant(),
            'eventloketToken' => $this->eventloketToken,
        ]);
    }
}
