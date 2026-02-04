<?php

namespace App\Filament\Organiser\Pages;

use App\Http\Middleware\ValidateOpenFormsPrefill;
use App\Models\FormsubmissionSession;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;

class NewRequest extends Page
{
    #[Locked]
    public $formId;

    #[Locked]
    public $prefillZaakReference = null;

    public $loadForm = false;

    protected static ?string $slug = 'new-request/{openform?}';

    protected static ?int $navigationSort = 1;

    // protected static string|array $routeMiddleware = ValidateOpenFormsPrefill::class; // prefill causes issues with openform submission, workaround for now is to save the of submission id from localstorage to the db

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.organiser.pages.new-request';

    // allow openform to do subrouting
    public static function routes(Panel $panel): void
    {
        Route::get(static::getRoutePath($panel), static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName($panel))
            ->where('openform', '.*');
    }

    public function mount(): void
    {
        $this->formId = config('services.open_forms.main_form_uuid');

        // Retrieve prefill_zaak_reference from session if available
        if (session()->has('prefill_zaak_reference')) {
            $this->prefillZaakReference = session()->pull('prefill_zaak_reference');
        }
    }

    public function checkInitialLoad()
    {
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();
        /** @var OrganiserUser $user */
        $user = Filament::auth()->user();
        /** @var FormsubmissionSession $submissionSession */
        $submissionSession = $user->formsubmissionSessions()->where('organisation_id', $tenant->id)->latest()->first();

        if ($submissionSession && ! $this->prefillZaakReference) {
            $this->js('loadFormWithRef', '"'.$submissionSession->uuid.'"');
        } else {
            $this->js('loadForm();');
        }
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

    public function updateFormsubmissionSession(string $submissionUUid)
    {
        $submissionUUid = trim($submissionUUid, '"');
        if ($submissionUUid && $submissionUUid != 'null' && $user = Filament::auth()->user()) {
            if ($this->prefillZaakReference) {
                $prefill_zaak_reference = $this->prefillZaakReference;
                $this->prefillZaakReference = null;
            } else {
                $prefill_zaak_reference = null;
            }
            /** @var Organisation $tenant */
            $tenant = Filament::getTenant();
            /** @var OrganiserUser $user */
            $resp = $user->formsubmissionSessions()->firstOrCreate(['uuid' => $submissionUUid], ['user_id' => $user->id, 'organisation_id' => $tenant->id, 'prefill_zaak_reference' => $prefill_zaak_reference]);
        }
    }
}
