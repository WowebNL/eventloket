<?php

declare(strict_types=1);

/**
 * A field that was filled in and then stopped being asked used to keep its
 * value. `FormState::absorbFields()` merges and never drops a key, so once a
 * field became hidden its last value stayed in the bag: invisible in the
 * interface, still present in everything built from that state. The organiser
 * could see an answer disappear from the form and still find it back in the
 * submitted application.
 *
 * The same happened one level up, for whole steps: the wizard only dims a step
 * the current answers make irrelevant, so its fields count as visible to
 * Filament and their values travelled along as well.
 *
 * All four outputs are built from that one state — the ZGW zaakeigenschappen,
 * the reference data, the stored form state snapshot and the submission report
 * behind the PDF — so each test asserts on all four.
 *
 * The scenarios that run through `submit()` prove the pruning is wired into
 * the submit path. The ones that need a melding or a permit application drive
 * the prune directly, because reaching `submit()` there means filling in every
 * required field of ten more steps; `absorbAndPruneLikeSubmit()` performs
 * exactly the two calls `submit()` makes.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\PrefillLoader;
use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\State\FormState;
use App\EventForm\Submit\MapFormStateToReferenceData;
use App\EventForm\Submit\SubmitEventForm;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\EventForm\Pages\FakeSubmitEventForm;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Notification::fake();

    $this->user = User::factory()->state(['role' => Role::Organiser])->create();
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach(
        $this->organisation->id,
        ['role' => OrganisationRole::Admin->value],
    );

    $this->actingAs($this->user);
    Filament::setTenant($this->organisation);
});

/**
 * The valid answers of a vooraankondiging. Taken from the submit guard test:
 * this path makes every conditional step non-applicable, so `submit()` only
 * has to get past the required fields of the base steps.
 *
 * @return array<string, mixed>
 */
function geldigeVooraankondiging(): array
{
    $morgen = now()->addDay()->format('Y-m-d');

    return [
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Test',
        'watIsUwEMailadres' => 'jan@example.com',
        'watIsUwTelefoonnummer' => '0612345678',

        'watIsDeNaamVanHetEvenementVergunning' => 'Test Evenement',
        'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning' => 'Een omschrijving.',
        'soortEvenement' => 'Festival',

        'waarVindtHetEvenementPlaats' => ['gebouw'],
        'adresVanDeGebouwEn' => [
            ['naamVanDeLocatieGebouw' => 'Stadhuis Heerlen'],
        ],

        'EvenementStart' => $morgen.' 10:00',
        'EvenementEind' => $morgen.' 18:00',
        'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Nee',
        'zijnErTijdensHetEvenementXOpbouwactiviteiten' => 'Nee',
        'zijnErAansluitendAanHetEvenementAfbouwactiviteiten' => 'Nee',
        'zijnErTijdensHetEvenementXAfbouwactiviteiten3' => 'Nee',

        'postcode1' => '6411CD',
        'straatnaam1' => 'Teststraat',
        'huisnummer1' => '1',
        'plaatsnaam1' => 'Heerlen',

        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',

        'akkoordVerwerkingGegevens' => true,
    ];
}

/** A live page on an empty draft, with a user and organisation the submit accepts. */
function pruneTestPage(User $user, Organisation $organisation): EventFormPage
{
    $draft = Draft::create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'state' => FormState::empty()->toSnapshot(),
    ]);

    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $draft->id])->instance();

    $page->state()->setSystem('authUser', OrganiserUser::find($user->id));
    $page->state()->setSystem('authOrganisation', $organisation);

    return $page;
}

/**
 * Put answers on the page the way the wizard would have left them: in
 * Livewire's data as well as in the state.
 *
 * @param  array<string, mixed>  $answers
 * @param  array<string, mixed>  $stale  values that only live in the state,
 *                                       as a copied or resumed application
 *                                       leaves them behind
 */
function beantwoord(EventFormPage $page, array $answers, array $stale = []): void
{
    $page->data = array_merge($page->data ?? [], $answers, $stale);
    $page->state()->absorbFields(array_merge($answers, $stale));
}

/** The two calls `submit()` makes between validation and handing the state over. */
function absorbAndPruneLikeSubmit(EventFormPage $page): void
{
    $form = $page->getSchema('form');

    $dehydrationState = [];
    $form->callBeforeStateDehydrated($dehydrationState);

    $absorb = new ReflectionMethod($page, 'absorbFormData');
    $absorb->setAccessible(true);
    $absorb->invoke($page, $form->getStateSnapshot());

    $prune = new ReflectionMethod($page, 'pruneStateToVisible');
    $prune->setAccessible(true);
    $prune->invoke($page);
}

/** Submits for real and returns the state `SubmitEventForm` was handed. */
function stateNaSubmit(EventFormPage $page): FormState
{
    $fake = new FakeSubmitEventForm(resultaat: Zaak::factory()->create([
        'organisation_id' => $page->state()->get('authOrganisation')->id,
        'organiser_user_id' => $page->state()->get('authUser')->id,
        'zaaktype_id' => Zaaktype::factory()->create([
            'municipality_id' => Municipality::factory()->create()->id,
        ])->id,
    ]));
    app()->instance(SubmitEventForm::class, $fake);

    $page->submit();

    expect($fake->aantalAanroepen)->toBe(1)
        ->and($fake->ontvangenState)->toBeInstanceOf(FormState::class);

    return $fake->ontvangenState;
}

/**
 * Everything an application produces from its state, as JSON so a test can
 * look for a value without knowing the shape of each output.
 *
 * @return array<string, string>
 */
function uitgaandeGegevens(FormState $state): array
{
    return [
        'zaakeigenschappen' => json_encode(
            app(ZaakeigenschappenMap::class)->buildEigenschappen($state),
            JSON_THROW_ON_ERROR,
        ),
        'reference_data' => json_encode(
            app(MapFormStateToReferenceData::class)
                ->build($state, 'Ingediend', 'https://zgw.example.com/statustypen/1')
                ->toArray(),
            JSON_THROW_ON_ERROR,
        ),
        'form_state_snapshot' => json_encode($state->toSnapshot()['values'], JSON_THROW_ON_ERROR),
        'submission_report' => json_encode(
            app(SubmissionReport::class)->build($state, EventFormSchema::stepsForReport()),
            JSON_THROW_ON_ERROR,
        ),
    ];
}

/** Fails naming the output that still carries the value. */
function verwachtNergensIn(FormState $state, string $needle): void
{
    foreach (uitgaandeGegevens($state) as $naam => $json) {
        expect(str_contains($json, $needle))->toBeFalse("'{$needle}' still ends up in {$naam}");
    }
}

function verwachtOveralIn(FormState $state, string $needle): void
{
    foreach (uitgaandeGegevens($state) as $naam => $json) {
        expect(str_contains($json, $needle))->toBeTrue("'{$needle}' is missing from {$naam}");
    }
}

/** The value ZGW is given for one zaakeigenschap, or null when it is left out. */
function zaakeigenschap(FormState $state, string $naam): mixed
{
    foreach (app(ZaakeigenschappenMap::class)->buildEigenschappen($state) as $eigenschap) {
        if (array_key_exists($naam, $eigenschap)) {
            return $eigenschap[$naam];
        }
    }

    return null;
}

/**
 * Renders one of the overview components of the live wizard and returns its
 * HTML, by evaluating the component the organiser actually looks at.
 */
function overzichtHtml(EventFormPage $page, string $naam): string
{
    foreach ($page->getSchema('form')->getFlatComponents(withActions: false, withHidden: true) as $component) {
        if (method_exists($component, 'getName') && $component->getName() === $naam) {
            return (string) $component->getState();
        }
    }

    throw new RuntimeException("overview component [{$naam}] not found in the form");
}

test('a location the organiser no longer ticks does not reach the outputs', function () {
    // The copied application took place outdoors; this one is in a building.
    // The outdoor location field is hidden from the moment the tick is gone,
    // so its name is an answer the organiser cannot see, let alone correct.
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, geldigeVooraankondiging(), [
        'locatieSOpKaart' => [['naamVanDeLocatieKaart' => 'WEGGEKLIKT-BUITENTERREIN']],
    ]);

    $state = stateNaSubmit($page);

    expect($state->get('locatieSOpKaart'))->toBeNull();
    verwachtNergensIn($state, 'WEGGEKLIKT-BUITENTERREIN');
});

test('a build-up time the organiser answered away does not reach the outputs', function () {
    // Build-up start and end are gated on a radio rather than on a rule of the
    // visibility engine, so this covers the other way a field gets hidden.
    // Both are mapped straight onto zaakeigenschappen, so a stale value is
    // handed to ZGW as the build-up window of this event.
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, geldigeVooraankondiging(), [
        'OpbouwStart' => '2035-01-01 08:00',
        'OpbouwEind' => '2035-01-01 09:00',
    ]);

    expect($page->state()->get('zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten'))->toBe('Nee');

    $state = stateNaSubmit($page);

    expect($state->get('OpbouwStart'))->toBeNull()
        ->and($state->get('OpbouwEind'))->toBeNull()
        ->and(zaakeigenschap($state, 'start_opbouw'))->toBeNull()
        ->and(zaakeigenschap($state, 'eind_opbouw'))->toBeNull();
    verwachtNergensIn($state, '2035-01-01');
});

test('the answers of a step that does not apply do not reach the outputs', function () {
    // A vooraankondiging has no risk scan and no melding. Both steps stay
    // clickable but dimmed, so Filament considers their fields visible; only
    // the applicability layer knows they are not being asked.
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, geldigeVooraankondiging(), [
        'watIsDeAantrekkingskrachtVanHetEvenement' => '3',
        'watIsHetAantalGelijktijdigAanwezigPersonen' => '3',
        'isErSprakeVanOvernachten' => '3',
        'wordtErAlcoholGeschonkenTijdensUwEvenement' => 'Ja',
    ]);

    $state = stateNaSubmit($page);

    expect($state->get('watIsDeAantrekkingskrachtVanHetEvenement'))->toBeNull()
        ->and($state->get('watIsHetAantalGelijktijdigAanwezigPersonen'))->toBeNull()
        ->and($state->get('wordtErAlcoholGeschonkenTijdensUwEvenement'))->toBeNull()
        ->and($state->get('risicoClassificatie'))->toBeNull()
        ->and(zaakeigenschap($state, 'risico_classificatie'))->toBeNull();
});

test('a melding does not carry the answers of a permit application', function () {
    // Every screening question answered "Ja" keeps the application a melding,
    // which makes the permit steps non-applicable.
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, array_merge(geldigeVooraankondiging(), [
        'waarvoorWiltUEventloketGebruiken' => 'aanvraag',
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Ja',
        'vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen' => 'Ja',
        'WordtErAlleenMuziekGeluidGeproduceerdTussen' => 'Ja',
        'IsdeGeluidsproductieLagerDan' => 'Ja',
        'erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten' => 'Ja',
        'wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst' => 'Ja',
        'indienErObjectenGeplaatstWordenZijnDezeDanKleiner' => 'Ja',
        // "Nee" here keeps it a melding and takes the risk scan step out of
        // the flow; "Ja" would be the one screening answer that asks for a
        // permit instead.
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]), [
        'watIsDeAantrekkingskrachtVanHetEvenement' => '5',
        'watIsHetAantalGelijktijdigAanwezigPersonen' => '5',
    ]);

    expect($page->state()->get('isVergunningaanvraag'))->not->toBe(true);

    absorbAndPruneLikeSubmit($page);
    $state = $page->state();

    // The screening answers themselves are asked on an applicable step and
    // stay, so the application keeps being a melding after the prune.
    expect($state->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf'))->toBe('Ja')
        ->and($state->get('isVergunningaanvraag'))->not->toBe(true)
        ->and($state->get('watIsDeAantrekkingskrachtVanHetEvenement'))->toBeNull()
        ->and($state->get('watIsHetAantalGelijktijdigAanwezigPersonen'))->toBeNull()
        ->and(zaakeigenschap($state, 'risico_classificatie'))->toBeNull();
});

test('a permit application keeps the risk scan it is asked to fill in', function () {
    // One "Nee" turns the application into a permit application; the risk scan
    // step applies from that moment and its answers have to survive.
    $risicoscan = [
        'watIsDeAantrekkingskrachtVanHetEvenement' => '1',
        'watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep' => '1',
        'isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid' => '1',
        'isEenDeelVanDeDoelgroepVerminderdZelfredzaam' => '0',
        'isErSprakeVanAanwezigheidVanRisicovolleActiviteiten' => '0',
        'watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep' => '1',
        'isErSprakeVanOvernachten' => '0',
        'isErGebruikVanAlcoholEnDrugs' => '1',
        'watIsHetAantalGelijktijdigAanwezigPersonen' => '1',
        'inWelkSeizoenVindtHetEvenementPlaats' => '0',
        'inWelkeLocatieVindtHetEvenementPlaats' => '0',
        'opWelkSoortOndergrondVindtHetEvenementPlaats' => '0',
        'watIsDeTijdsduurVanHetEvenement' => '0',
        'welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing' => '0',
    ];

    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, array_merge(geldigeVooraankondiging(), $risicoscan, [
        'waarvoorWiltUEventloketGebruiken' => 'aanvraag',
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Nee',
    ]));

    expect($page->state()->get('isVergunningaanvraag'))->toBeTrue();

    absorbAndPruneLikeSubmit($page);
    $state = $page->state();

    expect($state->get('isVergunningaanvraag'))->toBeTrue()
        ->and($state->get('watIsDeAantrekkingskrachtVanHetEvenement'))->not->toBeNull()
        ->and($state->get('isErSprakeVanOvernachten'))->not->toBeNull()
        ->and(zaakeigenschap($state, 'risico_classificatie'))->toBe('A');
});

test('an application without withdrawn answers comes out of the prune unchanged', function () {
    // The anchor for the other direction: with nothing stale in the state, the
    // four outputs have to be byte for byte what they were before the prune.
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, geldigeVooraankondiging());

    $form = $page->getSchema('form');
    $dehydrationState = [];
    $form->callBeforeStateDehydrated($dehydrationState);

    $absorb = new ReflectionMethod($page, 'absorbFormData');
    $absorb->setAccessible(true);
    $absorb->invoke($page, $form->getStateSnapshot());

    $voor = uitgaandeGegevens(FormState::fromSnapshot($page->state()->toSnapshot()));

    $prune = new ReflectionMethod($page, 'pruneStateToVisible');
    $prune->setAccessible(true);
    $prune->invoke($page);

    $na = uitgaandeGegevens($page->state());

    foreach (['zaakeigenschappen', 'reference_data', 'submission_report'] as $uitgaand) {
        expect($na[$uitgaand])->toBe($voor[$uitgaand], "{$uitgaand} changed while nothing was withdrawn");
    }

    // The snapshot is the one output that does change: it is the bag itself,
    // and it loses the empty keys Filament never dehydrated. No answer may
    // disappear from it, though.
    foreach (geldigeVooraankondiging() as $key => $value) {
        expect($page->state()->get($key))->not->toBeNull("answer {$key} was dropped");
    }
});

test('an answered gate that keeps its field visible keeps the value', function () {
    // The mirror image of the leak: as long as the organiser can see the
    // field, the value stays, however conditional the field is.
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, array_merge(geldigeVooraankondiging(), [
        'waarVindtHetEvenementPlaats' => ['gebouw', 'buiten'],
        'locatieSOpKaart' => [['naamVanDeLocatieKaart' => 'INGETEKEND-BUITENTERREIN']],
        'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Ja',
        'OpbouwStart' => now()->addDay()->format('Y-m-d').' 08:00',
        'OpbouwEind' => now()->addDay()->format('Y-m-d').' 09:00',
    ]));

    absorbAndPruneLikeSubmit($page);
    $state = $page->state();

    expect($state->get('locatieSOpKaart'))->not->toBeNull()
        ->and($state->get('OpbouwStart'))->not->toBeNull()
        ->and($state->get('OpbouwEind'))->not->toBeNull()
        ->and(zaakeigenschap($state, 'start_opbouw'))->not->toBeNull()
        ->and(json_encode($state->toSnapshot()['values'], JSON_THROW_ON_ERROR))
        ->toContain('INGETEKEND-BUITENTERREIN');
});

test('an unanswered location question is stopped by the step validation, not by the prune', function () {
    // `LocationKinds` treats an unanswered location question as "every kind
    // counts". That contract lives in the mapping layer and is untouched here:
    // the question is required, so a submit without an answer never gets as
    // far as the state being pruned.
    $page = pruneTestPage($this->user, $this->organisation);
    $antwoorden = geldigeVooraankondiging();
    unset($antwoorden['waarVindtHetEvenementPlaats']);
    beantwoord($page, $antwoorden);

    $fake = new FakeSubmitEventForm;
    app()->instance(SubmitEventForm::class, $fake);

    expect(fn () => $page->submit())->toThrow(ValidationException::class);
    expect($fake->aantalAanroepen)->toBe(0)
        ->and($page->state()->get('adresVanDeGebouwEn'))->not->toBeNull();
});

test('a copied application is submitted without the answers of its source', function () {
    // The copy loads the full snapshot of the source application, so every
    // conditional answer of that application travels along. Ticking a
    // different location kind hides the copied one, and that is where it used
    // to stay: out of sight, still on its way to ZGW and the PDF.
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => Municipality::factory()->create()->id,
        'is_active' => true,
    ]);
    $bron = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'organiser_user_id' => $this->user->id,
        'form_state_snapshot' => ['values' => [
            'watIsDeNaamVanHetEvenementVergunning' => 'Vorig jaar',
            'waarVindtHetEvenementPlaats' => ['buiten'],
            'locatieSOpKaart' => [['naamVanDeLocatieKaart' => 'VORIG-JAAR-BUITENTERREIN']],
            'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Ja',
            'OpbouwStart' => '2035-01-01 08:00',
            'OpbouwEind' => '2035-01-01 09:00',
        ], 'system' => []],
    ]);

    $gekopieerd = (new PrefillLoader)->load((string) $bron->id, $this->user, $this->organisation);
    $draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => $gekopieerd->toSnapshot(),
    ]);

    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $draft->id])->instance();
    $page->state()->setSystem('authUser', OrganiserUser::find($this->user->id));
    $page->state()->setSystem('authOrganisation', $this->organisation);

    // This year it is indoors and there is no build-up.
    beantwoord($page, geldigeVooraankondiging());

    $state = stateNaSubmit($page);

    verwachtNergensIn($state, 'VORIG-JAAR-BUITENTERREIN');
    verwachtNergensIn($state, '2035-01-01');
});

test('a resumed draft is submitted without the answers it no longer shows', function () {
    // A draft that was filled in over several sessions keeps everything that
    // was ever typed, including answers to questions that are no longer asked.
    $draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => ['values' => [
            'waarVindtHetEvenementPlaats' => ['buiten'],
            'locatieSOpKaart' => [['naamVanDeLocatieKaart' => 'EERDERE-SESSIE-BUITENTERREIN']],
            'watIsDeAantrekkingskrachtVanHetEvenement' => '5',
        ], 'system' => []],
    ]);

    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $draft->id])->instance();
    $page->state()->setSystem('authUser', OrganiserUser::find($this->user->id));
    $page->state()->setSystem('authOrganisation', $this->organisation);

    beantwoord($page, geldigeVooraankondiging());

    $state = stateNaSubmit($page);

    expect($state->get('watIsDeAantrekkingskrachtVanHetEvenement'))->toBeNull();
    verwachtNergensIn($state, 'EERDERE-SESSIE-BUITENTERREIN');
});

/**
 * The two places the wizard shows back what has been filled in: the times
 * overview on the times step, and the summary before submitting. Both used to
 * read the raw state, so a copied application that no longer has build-up
 * activities still listed the build-up times of the application it came from.
 * The times were kept out of the submitted application, but the organiser saw
 * them right up to the moment of submitting.
 */

/** A copy that brought build-up and take-down times along. */
function tijdenVanEenKopie(): array
{
    return [
        'OpbouwStart' => '2035-06-30 08:15',
        'OpbouwEind' => '2035-06-30 09:15',
        'AfbouwStart' => '2035-07-02 20:15',
        'AfbouwEind' => '2035-07-02 21:15',
    ];
}

test('build-up times the organiser answered away are gone from the times overview', function () {
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, array_merge(geldigeVooraankondiging(), [
        'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Nee',
        'zijnErAansluitendAanHetEvenementAfbouwactiviteiten' => 'Nee',
    ]), tijdenVanEenKopie());

    expect(overzichtHtml($page, 'overzichtTijden'))->not->toContain('2035');
});

test('build-up times the organiser answered away are gone from the summary', function () {
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, array_merge(geldigeVooraankondiging(), [
        'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Nee',
        'zijnErAansluitendAanHetEvenementAfbouwactiviteiten' => 'Nee',
    ]), tijdenVanEenKopie());

    $samenvatting = overzichtHtml($page, 'samenvattingOverzicht');

    expect($samenvatting)->not->toContain('2035')
        ->and($samenvatting)->not->toContain('Opbouw')
        ->and($samenvatting)->not->toContain('Afbouw');
});

test('the overviews leave the state itself alone', function () {
    // Only submitting settles what the answers are. Until then the wizard has
    // to be able to show the times again the moment the question comes back,
    // and the stored draft keeps its own bag.
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, array_merge(geldigeVooraankondiging(), [
        'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Nee',
    ]), tijdenVanEenKopie());

    overzichtHtml($page, 'overzichtTijden');
    overzichtHtml($page, 'samenvattingOverzicht');

    expect($page->state()->get('OpbouwStart'))->toBe('2035-06-30 08:15')
        ->and($page->stateAsAsked()->get('OpbouwStart'))->toBeNull();
});

test('times the organiser is asked for stay in both overviews', function () {
    // The other direction: as long as the question is being asked, the
    // overviews show exactly what they showed before.
    $page = pruneTestPage($this->user, $this->organisation);
    beantwoord($page, array_merge(geldigeVooraankondiging(), tijdenVanEenKopie(), [
        'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten' => 'Ja',
        'zijnErAansluitendAanHetEvenementAfbouwactiviteiten' => 'Ja',
    ]));

    expect(overzichtHtml($page, 'overzichtTijden'))->toContain('2035')
        ->and(overzichtHtml($page, 'samenvattingOverzicht'))->toContain('2035');

    // And nothing else is filtered either: with every answer still being
    // asked, the summary is byte for byte the one the raw state produces.
    $gevraagd = app(SubmissionReport::class)->build($page->stateAsAsked(), EventFormSchema::stepsForReport());
    $rauw = app(SubmissionReport::class)->build($page->state(), EventFormSchema::stepsForReport());

    expect(json_encode($gevraagd, JSON_THROW_ON_ERROR))->toBe(json_encode($rauw, JSON_THROW_ON_ERROR));
});
