<?php

declare(strict_types=1);

namespace App\Filament\Organiser\Pages;

use App\EventForm\Persistence\DraftStore;
use App\EventForm\Persistence\PrefillLoader;
use App\EventForm\Rules\RulesEngine;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;

/**
 * Volledige Filament-variant van het evenementformulier: 17 stappen, 144
 * rules via de RulesEngine, 4 service-fetches via ServiceFetcher, prefill
 * + draft-save via bestaande Persistence-laag.
 *
 * Vervangt op termijn de POC `EventForm.php` + `NewRequest.php` iframe-
 * embed (zie Stap 8 Cleanup).
 */
class EventFormPage extends Page implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /**
     * Livewire kan alleen primitives/arrays over de wire serializeren, dus
     * houden we een snapshot bij als array. `$state` (niet Livewire-synced)
     * wordt per request vanuit deze snapshot gehydrateerd.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $stateSnapshot = [];

    protected FormState $state;

    public function state(): FormState
    {
        return $this->state;
    }

    protected static ?string $slug = 'aanvraag';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-plus';

    protected string $view = 'filament.organiser.pages.event-form-page';

    public function mount(): void
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();

        $prefill = app(PrefillLoader::class)->load(
            request()->query('initial_data_reference'),
            $user,
            $tenant,
        );
        $draft = app(DraftStore::class)->load($user, $tenant);

        $this->state = $prefill ?? $draft ?? FormState::empty();
        $this->state->setSystem('authUser', $user);
        $this->state->setSystem('authOrganisation', $tenant);

        app(ServiceFetcher::class)->fetch('eventloketSession', $this->state);
        app(RulesEngine::class)->evaluate($this->state);

        $this->stateSnapshot = $this->serializableSnapshot($this->state);
        $this->form->fill($this->state->fields());
    }

    public function hydrate(): void
    {
        // Rehydrate $state op elke Livewire-roundtrip vanuit de snapshot zodat
        // updated() en submit() met de correcte state werken.
        $this->state = FormState::fromSnapshot($this->stateSnapshot);

        // Re-attach non-serializable systeem-waarden (user + tenant).
        if (Filament::auth()->check()) {
            /** @var User $user */
            $user = Filament::auth()->user();
            $this->state->setSystem('authUser', $user);
        }
        if ($tenant = Filament::getTenant()) {
            $this->state->setSystem('authOrganisation', $tenant);
        }
    }

    /**
     * Verwijder niet-serialiseerbare waarden (Eloquent-modellen) uit de
     * snapshot die via de wire gaat.
     *
     * @return array<string, mixed>
     */
    private function serializableSnapshot(FormState $state): array
    {
        $snap = $state->toSnapshot();
        foreach (['authUser', 'authOrganisation'] as $k) {
            unset($snap['system'][$k]);
        }

        return $snap;
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Wizard::make(EventFormSchema::steps())
                    ->submitAction(
                        Action::make('submit')
                            ->label('Aanvraag indienen')
                            ->action('submit'),
                    ),
            ])
            ->statePath('data');
    }

    public function updated(string $propertyName): void
    {
        if (! str_starts_with($propertyName, 'data')) {
            return;
        }

        $this->state->absorbFields($this->data ?? []);
        app(RulesEngine::class)->evaluate($this->state);

        $user = $this->state->get('authUser');
        $org = $this->state->get('authOrganisation');
        if ($user instanceof User && $org instanceof Organisation) {
            app(DraftStore::class)->save($user, $org, $this->state, null);
        }

        $this->stateSnapshot = $this->serializableSnapshot($this->state);
    }

    public function submit(): void
    {
        // Stap 7 (CreateZaakFromFormState) vult de daadwerkelijke submit-flow
        // in. Voor nu alleen de draft-clear zodat we end-to-end kunnen testen.
        $user = $this->state->get('authUser');
        $org = $this->state->get('authOrganisation');
        if ($user instanceof User && $org instanceof Organisation) {
            app(DraftStore::class)->clear($user, $org);
        }

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Aanvraag (test) ingediend')
            ->body('Submit-flow wordt in stap 7 volledig aangesloten.')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Nieuwe evenement-aanvraag';
    }

    public static function getNavigationLabel(): string
    {
        return __('Nieuwe aanvraag');
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }
}
