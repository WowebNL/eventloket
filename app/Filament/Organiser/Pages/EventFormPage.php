<?php

declare(strict_types=1);

namespace App\Filament\Organiser\Pages;

use App\EventForm\Components\VerticalWizard;
use App\EventForm\Persistence\DraftStore;
use App\EventForm\Persistence\PrefillLoader;
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
 * Volledige Filament-variant van het evenementformulier. State-derivatie
 * loopt via pure-functionele klasses (FormDerivedState,
 * FormFieldVisibility, FormStepApplicability, FormSystemDerivedState);
 * service-calls (BAG-lookup, gemeente-fetch, evenementen-overlap) gaan
 * direct via ServiceFetcher in `updated()`. De oude RulesEngine met 144
 * JsonLogic-rules is verwijderd.
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

    /**
     * Idempotency-guard: zodra een submit-poging start, gaat deze op true.
     * Een tweede submit-call binnen dezelfde Livewire-component-lifetime
     * doet dan niets en voorkomt dat een zaak twee keer wordt aangemaakt
     * als de gebruiker dubbelklikt of een tab refresht tijdens submit.
     * Filament's button heeft `wire:loading.attr=disabled` ingebakken,
     * maar dat dekt alleen de "browser-knop is even bevroren"-flow —
     * niet bv. een netwerkglitch waardoor twee POSTs binnenkomen.
     */
    #[Locked]
    public bool $submitting = false;

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

        // Sessie-prefill: als de organisator nog geen draft heeft, vullen
        // we user/organisation-velden met wat we uit `eventloketSession`
        // weten. Vervangt de OF-rules `RuleF56a54dd`, `AlsBoolEn`,
        // `AlsBoolEnIsN` etc. die dit elke roundtrip deden — wij doen 't
        // één keer bij mount zodat user-edits niet overschreven worden.
        $this->applySessionPrefill();

        $this->stateSnapshot = $this->serializableSnapshot($this->state);
        $this->form->fill($this->state->fields());
    }

    /**
     * Eenmalige kopie van eventloketSession-data naar user/organisation-
     * velden in de FormState. Alleen velden die nog leeg zijn worden
     * gevuld — daarmee respecteert de prefill een halverwege-ingevulde
     * draft (anders zou een refresh user-input overschrijven).
     */
    private function applySessionPrefill(): void
    {
        $copy = function (string $sessionPath, string $field): void {
            $existing = $this->state->get($field);
            if ($existing !== null && $existing !== '') {
                return; // user heeft 't al ingevuld of een rule eerder; niet overschrijven
            }
            $value = $this->state->get($sessionPath);
            if ($value === null || $value === '' || $value === 'None') {
                return;
            }
            $this->state->setVariable($field, $value);
        };

        // OF-rule RuleF56a54dd — user-velden uit session.
        $copy('eventloketSession.user_first_name', 'watIsUwVoornaam');
        $copy('eventloketSession.user_last_name', 'watIsUwAchternaam');
        $copy('eventloketSession.user_email', 'watIsUwEMailadres');
        $copy('eventloketSession.user_phone', 'watIsUwTelefoonnummer');
        $copy('eventloketSession.kvk', 'watIsHetKamerVanKoophandelNummerVanUwOrganisatie');

        // OF-rule AlsBoolEnIsNie — organisatie-naam.
        $copy('eventloketSession.organisation_name', 'watIsDeNaamVanUwOrganisatie');
        // OF-rule AlsBoolEnIsN — organisatie-email.
        $copy('eventloketSession.organisation_email', 'emailadresOrganisatie');
        // OF-rule AlsBoolEnIsN0f284f5c — organisatie-telefoon.
        $copy('eventloketSession.organisation_phone', 'telefoonnummerOrganisatie');

        // OF-rule AlsBoolEn — organisatie-adres uitgeplozen.
        $copy('eventloketSession.organisation_address.postcode', 'postcode1');
        $copy('eventloketSession.organisation_address.houseNumber', 'huisnummer1');
        $copy('eventloketSession.organisation_address.houseLetter', 'huisletter1');
        $copy('eventloketSession.organisation_address.houseNumberAddition', 'huisnummertoevoeging1');
        $copy('eventloketSession.organisation_address.streetName', 'straatnaam1');
        $copy('eventloketSession.organisation_address.city', 'plaatsnaam1');
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
                            // UI-niveau guard: knop blijft uitgeschakeld zodra
                            // submit() is gestart, óók nadat de Livewire-
                            // roundtrip klaar is en vóór de redirect heeft
                            // plaatsgevonden. Filament's eigen
                            // wire:loading.attr=disabled dekt alleen het
                            // request-in-flight-moment; daarna zou de knop
                            // weer klikbaar zijn voordat de pagina
                            // doorlinkt naar de zojuist aangemaakte zaak.
                            ->disabled(fn () => $this->submitting)
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

        // Service-fetches die voorheen door fetch-rules in de RulesEngine
        // werden afgevuurd, staan nu hier direct. Per veld dat verandert
        // bepalen we welke fetches aan-kunnen — ServiceFetcher cachet
        // intern op input-hash, dus dezelfde state → geen werk.
        $this->triggerFetchesFor($propertyName);

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
        // Filament's wizard zet `?step=form.<step-uuid>` in de URL — de
        // `form.`-prefix is hun interne encoding voor "dit is een step-key
        // van de Wizard-component". Stripppen voor draft-save metadata.
        $step = request()->query('step');
        if (! is_string($step) || $step === '') {
            return null;
        }

        return str_starts_with($step, 'form.') ? substr($step, 5) : $step;
    }

    /**
     * Vuur de juiste ServiceFetcher::fetch()-calls af op basis van welk
     * veld zojuist veranderde. Vervangt de fetch-rules die voorheen
     * dezelfde mapping in de RulesEngine deden (`AlsBool47620576`,
     * `AlsBoolEnBoolEnBoolEvenementingemeenteBrkIdentificat`,
     * `AlsBoolEnIsNietGelijkAanNone*`). De ServiceFetcher cachet intern
     * op een input-hash, dus over-fetchen kost geen extra werk.
     */
    private function triggerFetchesFor(string $propertyName): void
    {
        $field = $this->fieldKeyFromProperty($propertyName);
        if ($field === null) {
            return;
        }

        $fetcher = app(ServiceFetcher::class);

        // userSelectGemeente bepaalt via FormDerivedState welke gemeente
        // er gekozen is, dus z'n update triggert dezelfde fetches als
        // een directe wijziging op evenementInGemeente.brk_identification.
        if (in_array($field, ['userSelectGemeente', 'evenementInGemeente'], true)) {
            $fetcher->fetch('gemeenteVariabelen', $this->state);
        }

        if (in_array($field, ['EvenementStart', 'EvenementEind', 'userSelectGemeente', 'evenementInGemeente'], true)) {
            $fetcher->fetch('evenementenInDeGemeente', $this->state);
        }

        if (in_array($field, ['locatieSOpKaart', 'routesOpKaart', 'adresVanDeGebouwEn'], true)) {
            $fetcher->fetch('inGemeentenResponse', $this->state);
            $this->resetStaleGemeenteKeuze();

            // Na een succesvolle BAG-lookup kan FormDerivedState een
            // gemeente afleiden (1 gemeente gevonden, of een eerder
            // gekozen userSelectGemeente). Trigger ook de afhankelijke
            // fetches — die zelfs zonder Livewire-update voor de afgeleide
            // `evenementInGemeente` moeten draaien anders blijven labels
            // als "Is het aantal aanwezigen minder dan {{ aanwezigen }}
            // personen?" leeg. ServiceFetcher cachet op input-hash, dus
            // een herhaalde no-op fetch is goedkoop.
            $fetcher->fetch('gemeenteVariabelen', $this->state);
            $fetcher->fetch('evenementenInDeGemeente', $this->state);
        }
    }

    /**
     * Cleart `userSelectGemeente` wanneer de zojuist berekende gemeente-
     * intersectie de eerdere keuze van de organisator twijfelachtig
     * maakt: route start+eindigt in dezelfde gemeente, terwijl 'ie wel
     * door ≥2 gemeenten gaat. In zo'n geval moet de keuze opnieuw
     * gemaakt worden — anders blijft een stale brk_identification de
     * `evenementInGemeente`-derivation aansturen.
     *
     * Migreert OF-rule `be547255-4a1b-4f37-96e8-919d5351e7a5`
     * (AlsIsGelijkAanTrueEnReductieVanEvenemen). OF gebruikte
     * `userSelectGemeente11` als trigger-marker (interne duplicate-key-
     * suffix); wij hebben alleen één veld, dus checken we dat direct.
     */
    private function resetStaleGemeenteKeuze(): void
    {
        $startEndEqual = $this->state->get('inGemeentenResponse.line.start_end_equal');
        if ($startEndEqual !== true) {
            return;
        }

        $namen = $this->state->get('evenementInGemeentenNamen');
        if (! is_array($namen) || count($namen) < 2) {
            return;
        }

        $pick = $this->state->get('userSelectGemeente');
        if (! is_string($pick) || $pick === '') {
            return;
        }

        $this->state->setVariable('userSelectGemeente', '');
        if (is_array($this->data)) {
            $this->data['userSelectGemeente'] = '';
        }
    }

    /**
     * Pak de root-veldnaam uit een Livewire-property-pad. Voor
     * `data.locatieSOpKaart.0.naamVanDeLocatieKaart` is dat
     * `locatieSOpKaart`. Geeft null wanneer de input geen `data.`-prefix
     * heeft of geen segment erachter.
     */
    private function fieldKeyFromProperty(string $propertyName): ?string
    {
        if (! str_starts_with($propertyName, 'data.')) {
            return null;
        }
        $rest = substr($propertyName, 5);
        $dot = strpos($rest, '.');

        return $dot === false ? $rest : substr($rest, 0, $dot);
    }

    public function submit(): void
    {
        // Idempotency-guard: een tweede submit-aanroep binnen dezelfde
        // component-lifetime is altijd een dubbele-klik of race, nooit
        // een legitieme actie — een succesvolle submit redirect immers
        // direct weg van deze pagina.
        if ($this->submitting) {
            return;
        }
        $this->submitting = true;

        $user = $this->state->get('authUser');
        $org = $this->state->get('authOrganisation');

        if (! $user instanceof OrganiserUser || ! $org instanceof Organisation) {
            $this->submitting = false;

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
            // Reset zodat de gebruiker kan retry'en nu de fout zichtbaar is —
            // anders zit hij vast op een doodlopend formulier.
            $this->submitting = false;

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
