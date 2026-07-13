<?php

declare(strict_types=1);

namespace App\Filament\Organiser\Pages;

use App\EventForm\Components\VerticalWizard;
use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\DraftStore;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\EventForm\Submit\SubmitEventForm;
use App\Filament\Organiser\Resources\Zaken\ZaakResource;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
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

    /** Id van het actieve concept (route-param `{draft}`). */
    #[Locked]
    public ?int $draftId = null;

    /**
     * Pure UUID (zonder `form.`-prefix) van de stap waar de gebruiker
     * is. Server-side bijgehouden via updateCurrentStep() omdat
     * `request()->query('step')` tijdens Livewire-roundtrips naar
     * `/livewire/update` wijst en de browser-query-string daar ontbreekt.
     */
    #[Locked]
    public ?string $currentStepKey = null;

    /** Weergavetekst van de laatste autosave, voor de indicator in de wizard. */
    public ?string $lastSavedLabel = null;

    protected FormState $state;

    public function state(): FormState
    {
        return $this->state;
    }

    protected static ?string $slug = 'aanvraag/{draft}';

    /**
     * Bereikbaar via het concepten-overzicht (EventFormDraftsPage), niet
     * via de navigatie — de route heeft immers een draft-id nodig.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.organiser.pages.event-form-page';

    /**
     * De default route-naam zou `aanvraag.{draft}` worden (slug met `/`
     * vervangen door `.`); dit geeft een nette, stabiele naam.
     */
    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'aanvraag.formulier';
    }

    public function mount(int|string $draft): void
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        /** @var Organisation $tenant */
        $tenant = Filament::getTenant();

        $draftModel = app(DraftStore::class)->findFor($user, $tenant, $draft);
        if (! $draftModel instanceof Draft) {
            // 404 i.p.v. 403: lekt niet of een concept van iemand anders bestaat.
            abort(404);
        }

        $this->draftId = $draftModel->id;
        $this->currentStepKey = $draftModel->current_step_key;

        $this->state = FormState::fromSnapshot($draftModel->state ?? []);
        $this->state->setSystem('authUser', $user);
        $this->state->setSystem('authOrganisation', $tenant);

        $this->notifyResumedDraft($draftModel);

        $this->state->setVariable('alleGemeenteNamen', Municipality::orderBy('name')->pluck('name')->implode(', '));

        app(ServiceFetcher::class)->fetch('eventloketSession', $this->state);

        // Sessie-prefill: als de organisator nog geen draft heeft, vullen
        // we user/organisation-velden met wat we uit `eventloketSession`
        // weten. Vervangt de OF-rules `RuleF56a54dd`, `AlsBoolEn`,
        // `AlsBoolEnIsN` etc. die dit elke roundtrip deden — wij doen 't
        // één keer bij mount zodat user-edits niet overschreven worden.
        $this->applySessionPrefill();

        // Een gehydrateerde draft kan al een ingevulde locatie hebben
        // (gebruiker keert terug naar een halve aanvraag). In dat geval
        // moeten we `inGemeentenResponse`, `gemeenteVariabelen` en
        // `evenementenInDeGemeente` proactief fetchen — anders blijven
        // labels als "Is het aantal aanwezigen minder dan {{ aanwezigen }}
        // personen?" leeg tot de gebruiker een veld aanraakt.
        $this->refreshFetchesFromExistingState();

        $this->stateSnapshot = $this->serializableSnapshot($this->state);
        $this->form->fill($this->state->fields());

        // Filament's $form->fill() filtert complex value-objects soms weg
        // wanneer een veld z'n schema niet rendert (bv. de Locatie-stap is
        // nog niet actief op het moment van fill). Voor onze Map::make()-
        // velden willen we wél dat de polygon-/lijn-state direct in
        // $this->data zit — anders gaat een page-reload na enkel tekenen
        // (zonder Volgende-klik) verloren. Herstel hand-handig.
        $values = $this->state->fields();
        foreach (['locatieSOpKaart', 'routesOpKaart', 'adresVanDeGebouwEn'] as $mapKey) {
            if (array_key_exists($mapKey, $values) && ! empty($values[$mapKey]) && empty($this->data[$mapKey])) {
                // Alleen overschrijven als form->fill() het veld niet heeft gevuld.
                // Als form->fill() wel heeft verwerkt, heeft afterStateHydrated al
                // UUID-string-keys toegewezen. Overschrijven met snapshot-data (die
                // integer-keys kan hebben) breekt Filament's getChildSchema()-lookup:
                // filled(0) === false, waardoor getChildSchema(0) null retourneert.
                $this->data[$mapKey] = $values[$mapKey];
            }
        }
    }

    /**
     * Maak de stille autosave expliciet wanneer de gebruiker een eerder
     * gevuld concept opent: een notificatie met naam + laatste
     * bewerkingsmoment, en een initiële waarde voor de autosave-
     * indicator. Een vers (leeg) concept krijgt geen melding.
     *
     * Komt de gebruiker via "Nieuwe aanvraag met deze gegevens"
     * (`?bron=hergebruik`, gezet door EventFormDraftsPage), dan is het
     * concept weliswaar gevuld maar gloednieuw — de melding benoemt dan
     * het hergebruik van een eerdere aanvraag i.p.v. "Concept hervat".
     */
    private function notifyResumedDraft(Draft $draft): void
    {
        $values = $draft->state['values'] ?? [];
        if (! is_array($values) || $values === []) {
            return;
        }

        $this->lastSavedLabel = $this->formatSavedAt($draft->updated_at ?? now());

        if (request()->query('bron') === 'hergebruik') {
            Notification::make()
                ->info()
                ->title('Eerdere aanvraag hergebruikt')
                ->body('Dit formulier is vooraf ingevuld met de gegevens van uw eerdere aanvraag. Controleer alle stappen en pas aan waar nodig. Uw antwoorden worden automatisch opgeslagen als nieuw concept.')
                ->send();

            return;
        }

        Notification::make()
            ->info()
            ->title('Concept hervat')
            ->body(sprintf(
                'U gaat verder met uw concept "%s", laatst bewerkt op %s. Uw antwoorden worden automatisch opgeslagen.',
                $draft->display_name,
                $draft->updated_at?->format('d-m-Y \o\m H:i'),
            ))
            ->send();
    }

    /** Tijd voor de autosave-indicator: alleen tijdstip als 't vandaag is. */
    private function formatSavedAt(CarbonInterface $moment): string
    {
        return $moment->isToday() ? $moment->format('H:i') : $moment->format('d-m-Y H:i');
    }

    /**
     * Triggert de service-fetches op basis van wat al in de state staat
     * (uit draft of prefill). Idempotent: ServiceFetcher's interne
     * input-hash-cache zorgt dat een ongewijzigde input niets opnieuw
     * doet.
     */
    private function refreshFetchesFromExistingState(): void
    {
        $fetcher = app(ServiceFetcher::class);

        // Heeft de state al een locatie / route / adres ingetekend?
        // Dan opnieuw door BAG-lookup zodat `inGemeentenResponse`
        // beschikbaar is voor de afgeleiden.
        $heeftLocatieInput = ! empty($this->state->get('locatieSOpKaart'))
            || ! empty($this->state->get('routesOpKaart'))
            || ! empty($this->state->get('adresVanDeGebouwEn'));
        if ($heeftLocatieInput) {
            $fetcher->fetch('inGemeentenResponse', $this->state);
        }

        // Een geselecteerde gemeente betekent dat we
        // `gemeenteVariabelen` + `evenementenInDeGemeente` kunnen
        // fetchen. Werkt ook zonder eerdere BAG-lookup als de gemeente
        // al via prefill bekend is.
        if (! empty($this->state->get('evenementInGemeente.brk_identification'))) {
            $fetcher->fetch('gemeenteVariabelen', $this->state);
            $fetcher->fetch('evenementenInDeGemeente', $this->state);
        }
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
        /** @var Organisation|null $organisation */
        $organisation = Filament::getTenant();

        return $form
            ->schema([
                VerticalWizard::make(EventFormSchema::steps($organisation))
                    ->stepApplicability(fn (string $stepKey): bool => $this->state->isStepApplicable($stepKey))
                    // Resume bij terugkeer: als de organisator weg is geweest
                    // (bv. naar Dashboard) en geen `?step=`-query-param meer
                    // heeft, opent de wizard op de step waar 'ie gebleven
                    // was via de in mount() geladen `currentStepKey`.
                    // Filament's eigen `getStartStep()` checkt eerst de
                    // query-param; alleen wanneer die afwezig is wordt deze
                    // closure geëvalueerd.
                    ->startOnStep(fn (): int => $this->resolveStartStep())
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

        // Kaart-state moet altijd direct gepersisteerd worden — een tekening
        // kan niet "later", en Vorige/Volgende komt typisch binnen het
        // throttle-window na een teken-actie.
        $isMapUpdate = str_contains($propertyName, 'locatieSOpKaart')
            || str_contains($propertyName, 'routesOpKaart');

        $now = time();
        if (! $isMapUpdate && $now - $this->lastDraftSaveAt < 10) {
            return;
        }
        $this->persistDraft();
        $this->lastDraftSaveAt = $now;
    }

    /**
     * Persisteer de huidige draft direct, los van de updated()-throttle.
     * Aangeroepen vanuit JS (Volgende-knop in de vertical-wizard) om te
     * garanderen dat de meest recente kaart-tekening en andere deferred-
     * gesynchroniseerde velden in de DB staan vóór de step-change.
     */
    public function saveDraftNow(): void
    {
        $this->state->absorbFields($this->data ?? []);
        $this->stateSnapshot = $this->serializableSnapshot($this->state);
        $this->persistDraft();
        $this->lastDraftSaveAt = time();
    }

    private function persistDraft(): void
    {
        $draft = $this->activeDraft();
        if ($draft === null) {
            // Concept in een andere tab verwijderd: stil overslaan, de
            // gebruiker krijgt bij z'n volgende page-load een 404.
            return;
        }

        app(DraftStore::class)->save($draft, $this->state, $this->currentStepKey);

        $this->lastSavedLabel = $this->formatSavedAt(now());
        $this->dispatch('event-form-draft-saved', time: $this->lastSavedLabel);
    }

    /**
     * Het actieve concept, ownership-scoped opgehaald zodat een
     * gemanipuleerd draftId nooit andermans concept raakt.
     */
    private function activeDraft(): ?Draft
    {
        $user = $this->state->get('authUser');
        $org = $this->state->get('authOrganisation');
        if (! ($user instanceof User) || ! ($org instanceof Organisation) || $this->draftId === null) {
            return null;
        }

        return app(DraftStore::class)->findFor($user, $org, $this->draftId);
    }

    /**
     * Door de wizard-JS aangeroepen bij elke stap-wissel (Volgende,
     * Vorige én sidebar-kliks). Houdt de huidige stap server-side bij
     * en persisteert 'm zodat hervatten op de juiste stap opent.
     */
    public function updateCurrentStep(string $stepKey): void
    {
        $clean = str_starts_with($stepKey, 'form.') ? substr($stepKey, 5) : $stepKey;
        if (! in_array($clean, EventFormSchema::stepUuidsInOrder(), true)) {
            return; // onbekende key uit een gemanipuleerde call negeren
        }

        $this->currentStepKey = $clean;

        $draft = $this->activeDraft();
        if ($draft !== null) {
            app(DraftStore::class)->saveStep($draft, $clean);
        }
    }

    /**
     * 1-based positie van de step waar de wizard moet openen. Wordt
     * door Filament's `Wizard::getStartStep()` aangeroepen wanneer
     * 'r geen `?step=`-query-param in de URL staat — dat is precies
     * de "resume bij terugkeer"-case waarvoor we hier de in mount()
     * geladen `currentStepKey` uit het concept pakken.
     *
     * Defaultet naar 1 (Contactgegevens) bij geen bewaarde / onbekende
     * step-key zodat een verse organisator gewoon vanaf het begin
     * start.
     */
    private function resolveStartStep(): int
    {
        $key = $this->currentStepKey;
        if ($key === null || $key === '') {
            return 1;
        }
        // De draft slaat de pure UUID op; defensieve strip voor het
        // geval een legacy-draft de `form.`-prefix nog meeschrijft.
        $cleanKey = str_starts_with($key, 'form.') ? substr($key, 5) : $key;

        foreach (EventFormSchema::stepUuidsInOrder() as $index => $uuid) {
            if ($uuid === $cleanKey) {
                return $index + 1;
            }
        }

        return 1;
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
            // Reactieve check tijdens het typen/tekenen: adressen tellen alleen
            // mee als hun gemeente al uit de auto-fill bekend is, zodat deze
            // synchrone call nooit een trage PDOK-lookup voor een adres doet.
            // De autoritatieve, volledige check draait op de gate (Volgende) in
            // `runLocationGate()`. Kaart-items (vlak/lijn) worden hier gewoon
            // reactief gedetecteerd — die hebben de invoer-race niet.
            $fetcher->fetch('inGemeentenResponse', $this->state, authoritativeAddresses: false);
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
     * Autoritatieve gemeentebepaling op de gate (Volgende in de locatiestap).
     * Draait de volledige location-check over alle huidige inputs (adressen,
     * vlakken én lijnen), inclusief de PDOK-fallback voor handmatig ingevoerde
     * adressen zonder bekende gemeente, en werkt de dependent-fetches en de
     * state-snapshot bij zodat de afgeleide `evenementInGemeente` en de
     * zichtbaarheidsregels (keuze-radio, bevestigingstekst) direct kloppen.
     *
     * De locatiestap (`LocatieVanHetEvenement2Step::afterValidation`) roept dit
     * aan en beslist daarna op basis van `evenementInGemeente` of de gebruiker
     * verder mag.
     */
    public function runLocationGate(): void
    {
        $this->state->absorbFields($this->data ?? []);

        $fetcher = app(ServiceFetcher::class);
        $fetcher->fetch('inGemeentenResponse', $this->state);
        $this->resetStaleGemeenteKeuze();
        $fetcher->fetch('gemeenteVariabelen', $this->state);
        $fetcher->fetch('evenementenInDeGemeente', $this->state);

        $this->stateSnapshot = $this->serializableSnapshot($this->state);
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

        // Server-side hervalidatie vóór verwerking. De wizard valideert
        // stap-voor-stap bij navigatie, maar een directe Livewire-aanroep
        // van submit() kan die stappen omzeilen.
        // Alleen applicabele stappen worden gevalideerd: niet-applicabele
        // stappen (bijv. vergunning-stappen in een melding-flow) hebben
        // lege verplichte velden die een geldige indiening zouden blokkeren.
        // Bewust VOOR $this->submitting = true: bij een validatiefout
        // mag de knop niet permanent uitgeschakeld blijven.
        $this->validateApplicableSteps();

        $this->submitting = true;

        // `getStateSnapshot()` roept intern NIET `callBeforeStateDehydrated()`
        // aan (in tegenstelling tot `getState()`). Daardoor wordt de
        // `beforeStateDehydrated`-hook van BaseFileUpload — die
        // `saveUploadedFiles()` aanroept — overgeslagen. Zonder die call
        // blijven TemporaryUploadedFile-objecten in `livewire-tmp/` staan
        // en worden ze nooit naar de definitieve opslag verplaatst.
        //
        // Oplossing: roep eerst `callBeforeStateDehydrated()` expliciet aan.
        // Dat triggert `saveUploadedFiles()` voor elk FileUpload-veld, dat
        // op zijn beurt de bestanden verplaatst en `$this->data` bijwerkt
        // met de permanente schijfpaden via `rawState()`. Daarna leest
        // `getStateSnapshot()` de bijgewerkte `$this->data` en retourneert
        // de correcte paden.
        $dehydrationState = [];
        $this->form->callBeforeStateDehydrated($dehydrationState);
        $this->state->absorbFields($this->form->getStateSnapshot());

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
            $zaak = app(SubmitEventForm::class)->execute($this->state, $user, $org, $this->activeDraft());
        } catch (\Throwable $e) {
            report($e);
            // Reset zodat de gebruiker kan retry'en nu de fout zichtbaar is —
            // anders zit hij vast op een doodlopend formulier.
            $this->submitting = false;

            Notification::make()
                ->danger()
                ->title('Aanvraag niet ingediend')
                ->body('Er is een fout opgetreden bij het indienen. Probeer het opnieuw of neem contact op met de beheerder.')
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

    /**
     * Valideer alle applicabele wizard-stappen. Stappen die op basis van
     * `FormState::isStepApplicable()` niet van toepassing zijn (bijv.
     * vergunning-stappen in een melding-flow) worden overgeslagen zodat lege
     * verplichte velden in die stappen een geldige indiening niet blokkeren.
     *
     * Werpt een `ValidationException` zodra een stap met fouten stuit; Livewire
     * vangt dat af en stuurt de fouten naar de client.
     */
    private function validateApplicableSteps(): void
    {
        $components = $this->form->getComponents(withHidden: true);
        $wizard = $components[0] ?? null;
        if ($wizard === null) {
            return;
        }

        foreach ($wizard->getChildSchema()->getComponents() as $step) {
            // Filament prefixeert de step-key met "form." (de naam van de form-schema-
            // binding); COMPUTED_STEPS bevat alleen de kale UUID's. Dezelfde strip
            // staat in vertical-wizard.blade.php.
            $rawKey = $step->getKey();
            $stepUuid = str_starts_with($rawKey, 'form.') ? substr($rawKey, 5) : $rawKey;

            if (! $this->state->isStepApplicable($stepUuid)) {
                continue;
            }
            $step->getChildSchema()->validate();
        }
    }

    public function getTitle(): string
    {
        return 'Nieuwe aanvraag';
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
     * Header-actions op de aanvraag-pagina. "Concept verwijderen" gooit
     * alleen het actieve concept weg en stuurt terug naar het concepten-
     * overzicht; opnieuw of parallel beginnen loopt via dat overzicht,
     * zodat een nieuwe aanvraag nooit een ander concept sloopt.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('conceptVerwijderen')
                ->label('Concept verwijderen')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Concept verwijderen?')
                ->modalDescription('Hiermee verwijdert u dit concept en alle ingevulde gegevens. Dit kan niet ongedaan gemaakt worden.')
                ->modalSubmitActionLabel('Ja, verwijderen')
                ->action(function () {
                    $draft = $this->activeDraft();
                    if ($draft !== null) {
                        app(DraftStore::class)->delete($draft);
                    }
                    // Volledige page-reload naar het overzicht zodat de
                    // gebruiker daar een schoon concept kan starten of een
                    // ander concept kan kiezen.
                    $this->redirect(EventFormDraftsPage::getUrl(['tenant' => Filament::getTenant()]), navigate: false);
                }),
        ];
    }
}
