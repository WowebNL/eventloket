<?php

declare(strict_types=1);

/**
 * Idempotency-guard op EventFormPage::submit(). Achtergrond: de
 * opdrachtgever rapporteerde dat snel dubbel-klikken op "Aanvraag
 * indienen" ofwel niets deed (Filament's button-disabled grijpt al
 * in), ofwel — bij netwerkglitches — soms tóch twee zaken aanmaakte.
 *
 * Onze fix: een `$submitting`-vlag op de Page. De eerste submit-aanroep
 * zet 'm op true; een tweede submit-aanroep binnen dezelfde Livewire-
 * component-lifetime ziet de vlag en doet niets. Een succesvolle
 * submit redirect direct weg, en bij een fout wordt de vlag gereset
 * zodat de organisator kan retry'en.
 *
 * Server-side hervalidatie wordt per applicabele wizard-stap uitgevoerd
 * vóór de idempotency-guard. De guard-tests gebruiken het
 * 'vooraankondiging'-pad om alleen de basisstappen te valideren en
 * de conditionele vergunning-stappen te omzeilen.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\State\FormState;
use App\EventForm\Submit\SubmitEventForm;
use App\Exceptions\GemeenteLocatieMismatchException;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\EventForm\Pages\FakeSubmitEventForm;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->state(['role' => Role::Organiser])->create();
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach(
        $this->organisation->id,
        ['role' => OrganisationRole::Admin->value],
    );

    $this->actingAs($this->user);
    Filament::setTenant($this->organisation);

    // De pagina vereist een bestaand concept (route-param {draft}).
    $this->draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => FormState::empty()->toSnapshot(),
    ]);
});

/**
 * Geeft de minimale geldige formulierdata terug voor een
 * 'vooraankondiging'-aanvraag. Dit pad maakt alle conditionele
 * vergunning-stappen (RisicoscanStep, Vragenboom2Step, etc.) niet
 * applicable, zodat alleen de verplichte basisstappen gevalideerd worden.
 *
 * @return array<string, mixed>
 */
function minimalVooraankondigingData(): array
{
    $morgen = now()->addDay()->format('Y-m-d');

    return [
        // ContactgegevensStep — altijd applicable, altijd zichtbare verplichte velden
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Test',
        'watIsUwEMailadres' => 'jan@example.com',
        'watIsUwTelefoonnummer' => '0612345678',

        // NaamVanHetEvenementStep — naam vereist; omschrijving + soort zichtbaar ná naam
        'watIsDeNaamVanHetEvenementVergunning' => 'Test Evenement',
        'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning' => 'Een omschrijving van het test-evenement.',
        'soortEvenement' => 'Festival',

        // LocatieVanHetEvenement2Step — 'gebouw' toont het Repeater-veld (geen kaart nodig);
        // 'buiten' vereist een Map-tekening die niet in unit-tests te vullen is.
        'waarVindtHetEvenementPlaats' => ['gebouw'],
        // The repeater item carries an AddressNL group whose postcode, house
        // number, street and town have always been required. Server-side
        // revalidation reaches the fields inside a repeater item, so an item
        // without an address is not a valid answer.
        'adresVanDeGebouwEn' => [
            [
                'naamVanDeLocatieGebouw' => 'Stadhuis Heerlen',
                'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                    'postcode' => '6411CD',
                    'huisnummer' => '1',
                    'straatnaam' => 'Teststraat',
                    'woonplaatsnaam' => 'Heerlen',
                ],
            ],
        ],

        // TijdenStep — datum + 4 radios altijd zichtbaar en vereist
        'EvenementStart' => $morgen.' 10:00',
        'EvenementEind' => $morgen.' 18:00',
        'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Nee',
        'zijnErTijdensHetEvenementXOpbouwactiviteiten' => 'Nee',
        'zijnErAansluitendAanHetEvenementAfbouwactiviteiten' => 'Nee',
        'zijnErTijdensHetEvenementXAfbouwactiviteiten3' => 'Nee',

        // OrganisatieInformatieFieldset — zichtbaar omdat eventloketSession.kvk truthy is.
        // Adres komt niet uit de session (organisation_address is leeg in tests).
        'postcode1' => '6411CD',
        'straatnaam1' => 'Teststraat',
        'huisnummer1' => '1',
        'plaatsnaam1' => 'Heerlen',

        // WaarvoorWiltUHetEventloketGebruikenStep — 'vooraankondiging' maakt alle
        // conditionele stappen niet applicable (RisicoscanStep, Vragenboom2Step, etc.)
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',

        // SamenvattingStep — AVG-akkoord verplicht accepted
        'akkoordVerwerkingGegevens' => true,
    ];
}

/**
 * Bereidt de page voor zodat submit() doorkomt:
 *   - $data bevat geldige form-waarden voor het vooraankondiging-pad
 *   - state absorbeert die waarden (voor isStepApplicable)
 *   - authUser/authOrganisation zijn gezet als OrganiserUser/Organisation
 *
 * Direct op de page-instantie — niet via call(), dat gebruikt de Livewire-snapshot
 * die niet de in-memory mutaties ziet.
 */
function setupValidSubmit(EventFormPage $page, User $user, Organisation $organisation): void
{
    // Zet de geldige data direct op de component-eigenschap; dit omzeilt
    // updated()-throttling en draft-persistence, maar geeft validateApplicableSteps()
    // de waarden die het nodig heeft om door te komen.
    $data = minimalVooraankondigingData();
    $page->data = array_merge($page->data ?? [], $data);

    // Absorbeer in state zodat isStepApplicable('vooraankondiging') de conditionele
    // stappen als niet-van-toepassing markeert.
    $page->state()->absorbFields($page->data);

    // OrganiserUser nodig voor de instanceof-check in submit(); hydrate() zou
    // dit normaal invullen maar dat veronderstelt een correcte Filament-panel-
    // context die in tests niet gegarandeerd is.
    $page->state()->setSystem('authUser', OrganiserUser::find($user->id));
    $page->state()->setSystem('authOrganisation', $organisation);
}

test('twee submit-aanroepen achter elkaar maken maximaal één zaak aan', function () {
    // ZaakObserver dispatcht jobs en verstuurt mails; we hoeven die
    // niet te runnen voor deze guard-test, en factories vereisen
    // anders een complete municipality+zaaktype-keten.
    Bus::fake();
    Notification::fake();

    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
    $zaak = Zaak::factory()->create([
        'organisation_id' => $this->organisation->id,
        'organiser_user_id' => $this->user->id,
        'zaaktype_id' => $zaaktype->id,
    ]);
    $fake = new FakeSubmitEventForm(resultaat: $zaak);
    app()->instance(SubmitEventForm::class, $fake);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);
    /** @var EventFormPage $page */
    $page = $component->instance();
    setupValidSubmit($page, $this->user, $this->organisation);

    // Eerste submit: SubmitEventForm wordt aangeroepen, page zet
    // submitting=true, redirect naar de zaak-view.
    $page->submit();

    // Tweede submit: guard kicked in, SubmitEventForm wordt NIET
    // opnieuw aangeroepen. (In de praktijk landt deze tweede call
    // bijna nooit, want de eerste submit triggert een redirect; we
    // simuleren hier de race waarbij twee POSTs binnenkomen voor
    // de redirect het effect heeft.)
    $page->submit();

    expect($fake->aantalAanroepen)->toBe(1);
});

test('na een mislukte submit kan de organisator opnieuw proberen', function () {
    $fake = new FakeSubmitEventForm(
        gooitException: new RuntimeException('OpenZaak onbereikbaar'),
    );
    app()->instance(SubmitEventForm::class, $fake);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);
    /** @var EventFormPage $page */
    $page = $component->instance();
    setupValidSubmit($page, $this->user, $this->organisation);

    // Eerste poging mislukt; guard moet de `submitting`-vlag terugzetten
    // op false zodat een retry-klik wel doorgaat.
    $page->submit();
    $page->submit();

    expect($fake->aantalAanroepen)->toBe(2);
});

test('een gemeente die niet bij de locatie hoort levert een melding op die naar de locatiestap verwijst', function () {
    // De generieke "probeer het opnieuw" is hier misleidend: opnieuw indienen
    // geeft gegarandeerd dezelfde fout. De organisator moet terug naar de
    // locatiestap, en dat moet de melding ook zeggen.
    $fake = new FakeSubmitEventForm(
        gooitException: new GemeenteLocatieMismatchException('GM0917', ['GM0935']),
    );
    app()->instance(SubmitEventForm::class, $fake);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);
    /** @var EventFormPage $page */
    $page = $component->instance();
    setupValidSubmit($page, $this->user, $this->organisation);

    $page->submit();

    $component->assertNotified(
        FilamentNotification::make()
            ->danger()
            ->title('Aanvraag niet ingediend')
            ->body('De gemeente in uw aanvraag hoort niet bij de locatie die u heeft opgegeven. Ga terug naar de stap "Locatie", controleer het adres en bepaal de gemeente opnieuw.')
            ->persistent()
    );

    // De vlag moet gereset zijn: de organisator kan na het corrigeren van de
    // locatie opnieuw indienen zonder de pagina te herladen.
    expect($page->submitting)->toBeFalse();
});

test('submit() op leeg formulier gooit validatiefouten en roept SubmitEventForm niet aan', function () {
    $fake = new FakeSubmitEventForm;
    app()->instance(SubmitEventForm::class, $fake);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);
    /** @var EventFormPage $page */
    $page = $component->instance();

    // Geen form-data — de server-side validatie moet dit onderscheppen vóórdat
    // SubmitEventForm wordt aangeroepen.
    expect(fn () => $page->submit())
        ->toThrow(ValidationException::class);

    // SubmitEventForm mag niet aangeroepen zijn.
    expect($fake->aantalAanroepen)->toBe(0);

    // De $submitting-vlag mag niet permanent op true staan na een validatiefout;
    // anders is de submit-knop onterecht permanent uitgeschakeld.
    expect($page->submitting)->toBeFalse();
});
