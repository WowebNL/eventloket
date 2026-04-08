<?php

namespace App\Filament\Organiser\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;

class ReuseRequest extends Page
{
    #[Locked]
    public $formId;

    #[Locked]
    public $eventloketToken;

    #[Locked]
    public $initialDataReference;

    protected static ?string $slug = 'reuse-request/{eventloketToken}/{openform?}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.organiser.pages.reuse-request';

    public static function routes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        Route::get(static::getRoutePath($panel), static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName($panel))
            ->where('openform', '.*');
    }

    public function mount(string $eventloketToken): void
    {
        $this->formId = config('services.open_forms.main_form_uuid');
        $this->eventloketToken = $eventloketToken;
        $this->initialDataReference = request()->query('initial_data_reference');
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
}
