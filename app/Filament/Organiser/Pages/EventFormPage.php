<?php

declare(strict_types=1);

namespace App\Filament\Organiser\Pages;

use App\EventForm\Components\VerticalWizard;
use App\EventForm\Persistence\DraftStore;
use App\EventForm\Persistence\PrefillLoader;
use App\EventForm\Rules\RulesEngine;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\EventForm\Submit\SubmitEventForm;
use App\Filament\Organiser\Resources\Zaken\ZaakResource;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    /** Unix timestamp van de laatste draft-save. Gebruikt voor throttling. */
    #[Locked]
    public int $lastDraftSaveAt = 0;

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
            request()->query('prefill_from_zaak'),
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
                VerticalWizard::make(EventFormSchema::steps())
                    ->stepApplicability(fn (string $stepKey): bool => $this->state->isStepApplicable($stepKey))
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

        // Scoped evaluatie: run alleen rules die triggers hebben op de
        // huidige stap. Van ~144 naar typisch <10 rules per klik.
        $currentStep = $this->currentStepUuid();
        if ($currentStep !== null) {
            app(RulesEngine::class)->evaluateForStep($this->state, $currentStep);
        } else {
            // Fallback bij onbekende step (eerste load): globale pass.
            app(RulesEngine::class)->evaluate($this->state);
        }

        // Throttle draft-save: minimaal 10s tussen schrijfacties. Sla altijd
        // de in-memory snapshot op zodat hydratie werkt; de DB-write gaat
        // throttled.
        $this->stateSnapshot = $this->serializableSnapshot($this->state);

        $now = time();
        if ($now - $this->lastDraftSaveAt < 10) {
            return;
        }
        $user = $this->state->get('authUser');
        $org = $this->state->get('authOrganisation');
        if ($user instanceof User && $org instanceof Organisation) {
            app(DraftStore::class)->save($user, $org, $this->state, $this->currentStepUuid());
            $this->lastDraftSaveAt = $now;
        }
    }

    private function currentStepUuid(): ?string
    {
        $step = request()->query('step');

        return is_string($step) && $step !== '' ? $step : null;
    }

    public function submit(): void
    {
        $user = $this->state->get('authUser');
        $org = $this->state->get('authOrganisation');

        if (! $user instanceof OrganiserUser || ! $org instanceof Organisation) {
            Notification::make()
                ->danger()
                ->title('Aanvraag niet ingediend')
                ->body('We konden uw gebruiker of organisatie niet terugvinden. Log opnieuw in en probeer het nog eens.')
                ->send();

            return;
        }

        try {
            $zaak = app(SubmitEventForm::class)->execute($this->state, $user, $org);
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Aanvraag niet ingediend')
                ->body('Er ging iets mis bij het indienen: '.$e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Aanvraag ingediend')
            ->body(sprintf('Uw aanvraag is aangemaakt met zaaknummer %s.', $zaak->public_id))
            ->send();

        $this->redirect(
            ZaakResource::getUrl('view', ['record' => $zaak]),
            navigate: true,
        );
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

    /**
     * Header-actions op de aanvraag-pagina. "Begin opnieuw" leegt de
     * draft + state zodat de organisator een halfingevuld formulier
     * weg kan gooien en met een schoon formulier kan starten — handig
     * bij testen, bij wijziging van plan, of na een gedeelde sessie.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('beginOpnieuw')
                ->label('Begin opnieuw')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Begin opnieuw met een leeg formulier')
                ->modalDescription('Hierdoor verdwijnen alle ingevulde gegevens van deze aanvraag en begint u met een leeg formulier. Dit kan niet ongedaan gemaakt worden.')
                ->modalSubmitActionLabel('Ja, begin opnieuw')
                ->action(function () {
                    $user = $this->state->get('authUser');
                    $org = $this->state->get('authOrganisation');
                    if ($user instanceof User && $org instanceof Organisation) {
                        app(DraftStore::class)->clear($user, $org);
                    }
                    // Volledige page-reload zodat mount() opnieuw draait en
                    // alle session/state opnieuw wordt opgebouwd zonder resten
                    // van de oude wizard. NB: `request()->url()` werkt hier
                    // niet — tijdens een Livewire-action wijst die naar
                    // `/livewire/update` (de POST-endpoint), niet naar de
                    // browser-URL. Reconstrueer de aanvraag-URL via Filament's
                    // eigen route-resolver.
                    $this->redirect(static::getUrl(['tenant' => Filament::getTenant()]), navigate: false);
                }),
        ];
    }
}
