<?php

namespace App\Filament\Organiser\Pages;

use App\Http\Middleware\ValidateOpenFormsPrefill;
use App\Models\FormsubmissionSession;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;

class NewRequest extends Page
{
    #[Locked]
    public $formId;

    public $loadForm = false;

    protected static ?string $slug = 'new-request/{openform?}';

    protected static ?int $navigationSort = 1;

    protected static string | array $routeMiddleware = ValidateOpenFormsPrefill::class; // prefill causes issues with openform submission, workaround for now is to save the of submission id from localstorage to the db
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
    }

    public function getTitle(): string
    {
        return '';
    }

    public static function getNavigationLabel(): string
    {
        return __('organiser/pages/new-request.navigation_label');
    }

    public function updateFormsubmissionSession(string $submissionUUid)
    {
        $submissionUUid = trim($submissionUUid, '"');
        if ($submissionUUid && $user = Filament::auth()->user()) {
            /** @var Organisation $tenant */
            $tenant = Filament::getTenant();
            /** @var OrganiserUser $user */
            $resp = $user->formsubmissionSessions()->firstOrCreate(['uuid' => $submissionUUid], ['user_id' => $user->id, 'organisation_id' => $tenant->id]);
        }
    }

    public function checkSubmissionSession(string $submissionUUid)
    {
        $submissionUUid = trim($submissionUUid, '"');
        if ($submissionUUid && $user = Filament::auth()->user()) {
            /** @var Organisation $tenant */
            $tenant = Filament::getTenant();
            /** @var OrganiserUser $user */
            if (! $user->formsubmissionSessions()->where(['uuid' => $submissionUUid, 'organisation_id' => $tenant->id])->exists()) {
                $this->js('deleteStorageRef');
                if ($submission = $user->formsubmissionSessions()->where('organisation_id', $tenant->id)->latest()->first()) {
                    /** @var FormsubmissionSession $submission */
                    $this->js('loadFormWithRef', '"'.$submission->uuid.'"');
                } else {
                    $this->js('listenLocalStorage');
                    $this->js('loadForm');
                }

                return;
            }
        }
        $this->js('loadForm');
        $this->js('checkIfSubmissionChanges', '"'.$submissionUUid.'"');
    }

    public function checkLoadExistingSubmissionSession()
    {
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();
        /** @var OrganiserUser $user */
        $user = Filament::auth()->user();
        /** @var FormsubmissionSession $submissionSession */
        $submissionSession = $user->formsubmissionSessions()->where('organisation_id', $tenant->id)->latest()->first();

        if ($submissionSession) {
            $this->js('loadFormWithRef', '"'.$submissionSession->uuid.'"');
        } else {
            $this->js('listenLocalStorage');
            $this->js('loadForm');
        }
    }
}
