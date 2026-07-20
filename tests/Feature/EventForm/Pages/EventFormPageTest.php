<?php

declare(strict_types=1);

use App\Enums\MunicipalityVariableType;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\State\FormState;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Haal de `afterValidation`-gate-closure van de locatiestap op zodat we 'm
 * direct met een echte EventFormPage als `$livewire` kunnen aanroepen. Een
 * volledige wizard-navigatie zou de hele 18-staps wizard renderen en tegen de
 * memory-limit aanlopen; deze closure bevat de gate-beslislogica geïsoleerd.
 */
function locatieGateCallback(): Closure
{
    $step = LocatieVanHetEvenement2Step::make();
    $ref = new ReflectionProperty($step, 'afterValidation');
    $ref->setAccessible(true);

    /** @var Closure $callback */
    $callback = $ref->getValue($step);

    return $callback;
}

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    // De pagina vereist een bestaand concept (route-param {draft}).
    $this->draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => FormState::empty()->toSnapshot(),
        'current_step_key' => null,
    ]);
});

test('the page mounts for an authenticated user', function () {
    Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->assertOk();
});

test('mount seeds FormState with authUser and authOrganisation', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state())->toBeInstanceOf(FormState::class)
        ->and($page->state()->get('authUser'))->toBeInstanceOf(User::class)
        ->and($page->state()->get('authOrganisation'))->toBeInstanceOf(Organisation::class);
});

test('mount hydrates eventloketSession via ServiceFetcher', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state()->get('eventloketSession.user_uuid'))->toBe($this->user->uuid)
        ->and($page->state()->get('eventloketSession.organiser_uuid'))->toBe($this->organisation->uuid);
});

test('mount loads the draft state for the given draft id', function () {
    // Gebruik een veld-key die GEEN session-prefill voor zich heeft (zie
    // EventFormPage::applySessionPrefill), zodat de prefill de draft-
    // waarde niet alsnog overschrijft.
    $state = FormState::empty();
    $state->setField('watIsDeNaamVanHetEvenementVergunning', 'Eva se feest');
    $this->draft->update(['state' => $state->toSnapshot()]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state()->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Eva se feest');
});

test('mount toont een hervat-notificatie bij een eerder gevuld concept', function () {
    $state = FormState::empty();
    $state->setField('watIsDeNaamVanHetEvenementVergunning', 'Eva se feest');
    $this->draft->update(['state' => $state->toSnapshot(), 'name' => 'Eva se feest']);

    Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->assertNotified('Concept hervat');
});

test('mount toont een hergebruik-melding bij bron=hergebruik (i.p.v. "Concept hervat")', function () {
    // De flow "Nieuwe aanvraag met deze gegevens": EventFormDraftsPage
    // maakt een nieuw, gevuld concept en redirect met ?bron=hergebruik.
    $state = FormState::empty();
    $state->setField('watIsDeNaamVanHetEvenementVergunning', 'Buurtfeest 2027');
    $this->draft->update(['state' => $state->toSnapshot(), 'name' => 'Buurtfeest 2027']);

    Livewire::withQueryParams(['bron' => 'hergebruik'])
        ->test(EventFormPage::class, ['draft' => $this->draft->id])
        ->assertNotified('Eerdere aanvraag hergebruikt');
});

test('mount toont géén hervat-notificatie bij een leeg (vers) concept', function () {
    Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->assertNotNotified();
});

test('mount geeft 404 voor een concept van een andere gebruiker', function () {
    $otherUser = User::factory()->create(['role' => Role::Organiser]);
    $otherUser->organisations()->attach($this->organisation->id, ['role' => 'admin']);
    $otherDraft = Draft::create([
        'user_id' => $otherUser->id,
        'organisation_id' => $this->organisation->id,
        'state' => FormState::empty()->toSnapshot(),
    ]);

    $this->get(EventFormPage::getUrl(['draft' => $otherDraft->id, 'tenant' => $this->organisation]))
        ->assertNotFound();
});

test('mount geeft 404 voor een onbekend concept-id', function () {
    $this->get(EventFormPage::getUrl(['draft' => 999999, 'tenant' => $this->organisation]))
        ->assertNotFound();
});

test('updateCurrentStep persisteert de stap in de database (resume-schrijfpad)', function () {
    // Directe instance-call (zoals de andere tests in dit bestand): een
    // volledige Livewire-roundtrip rendert de hele 18-staps wizard
    // opnieuw en loopt in de testsuite tegen de memory-limit aan.
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();
    $page->updateCurrentStep('form.'.TijdenStep::UUID);

    expect($this->draft->refresh()->current_step_key)->toBe(TijdenStep::UUID);
});

test('updateCurrentStep negeert onbekende step-keys', function () {
    $this->draft->update(['current_step_key' => TijdenStep::UUID]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();
    $page->updateCurrentStep('gemanipuleerde-key');

    expect($this->draft->refresh()->current_step_key)->toBe(TijdenStep::UUID);
});

test('saveDraftNow persisteert direct en zet het autosave-label voor de indicator', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();
    $page->data['watIsDeNaamVanHetEvenementVergunning'] = 'Tussentijds opgeslagen';
    $page->saveDraftNow();

    expect($page->lastSavedLabel)->not->toBeNull()
        ->and($this->draft->refresh()->name)->toBe('Tussentijds opgeslagen');
});

test('conceptVerwijderen verwijdert het concept en stuurt terug naar het overzicht', function () {
    Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->callAction('conceptVerwijderen')
        ->assertRedirect();

    expect(Draft::whereKey($this->draft->id)->exists())->toBeFalse();
});

test('mount opent op de step waar de gebruiker gebleven was (uit draft)', function () {
    // De gebruiker stopte op de Tijden-stap (index 4 in EventFormSchema::steps()).
    $this->draft->update(['current_step_key' => TijdenStep::UUID]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();
    // Reflectie op de helper die de wizard-startindex bepaalt.
    $reflection = new ReflectionMethod($page, 'resolveStartStep');
    $reflection->setAccessible(true);

    $expectedIndex = array_search(
        TijdenStep::UUID,
        EventFormSchema::stepUuidsInOrder(),
        true,
    );
    expect($expectedIndex)->not->toBeFalse();
    expect($reflection->invoke($page))->toBe($expectedIndex + 1);
});

test('mount valt terug op stap 1 zonder current_step_key', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();
    $reflection = new ReflectionMethod($page, 'resolveStartStep');
    $reflection->setAccessible(true);

    expect($reflection->invoke($page))->toBe(1);
});

test('mount valt terug op stap 1 bij onbekende current_step_key', function () {
    $this->draft->update(['current_step_key' => 'this-is-not-a-real-step-uuid']);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();
    $reflection = new ReflectionMethod($page, 'resolveStartStep');
    $reflection->setAccessible(true);

    expect($reflection->invoke($page))->toBe(1);
});

test('route die start+eindigt in dezelfde gemeente maar door ≥2 gemeenten gaat → eerdere gemeente-keuze wordt geleegd', function () {
    // Bug-rapport-equivalent uit OF: de organisator had al een
    // gemeente gekozen (Heerlen), tekent dan een route die start+eindigt
    // in Heerlen maar door Maastricht ook loopt. Heerlen-keuze hoort
    // dan opnieuw bevestigd te worden — anders blijft 't gebaseerd op
    // een outdated route-state. Migreert OF-rule
    // be547255-4a1b-4f37-96e8-919d5351e7a5.
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    // Seed inGemeentenResponse via setVariable (state-level — niet door
    // Filament's form gerouteerd). userSelectGemeente moet in `$data`
    // staan want updated() doet absorbFields($data) als eerste actie.
    $page->state()->setVariable('inGemeentenResponse', [
        'all' => ['items' => [
            ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]],
        'line' => ['start_end_equal' => true],
    ]);
    $page->data['userSelectGemeente'] = 'GM0917';

    $page->updated('data.locatieSOpKaart');

    expect($page->state()->get('userSelectGemeente'))->toBe('')
        ->and($page->data['userSelectGemeente'])->toBe('');
});

test('zonder ≥2 gemeenten of zonder eerdere keuze → geen reset', function () {
    // Conditie 1: maar 1 gemeente in de response → niets te resetten
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    $page->state()->setVariable('inGemeentenResponse', [
        'all' => ['items' => [['brk_identification' => 'GM0917', 'name' => 'Heerlen']]],
        'line' => ['start_end_equal' => true],
    ]);
    $page->data['userSelectGemeente'] = 'GM0917';

    $page->updated('data.locatieSOpKaart');

    expect($page->state()->get('userSelectGemeente'))->toBe('GM0917');

    // Conditie 2: ≥2 gemeenten maar route start≠eind → keuze blijft
    $page->state()->setVariable('inGemeentenResponse', [
        'all' => ['items' => [
            ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]],
        'line' => ['start_end_equal' => false],
    ]);
    $page->data['userSelectGemeente'] = 'GM0917';

    $page->updated('data.locatieSOpKaart');

    expect($page->state()->get('userSelectGemeente'))->toBe('GM0917');
});

test('na adres-invul → gemeenteVariabelen wordt automatisch gefetched (label-placeholders)', function () {
    // Bug-rapport: het label "Is het aantal aanwezigen minder dan
    // {{ aanwezigen }} personen?" toonde leeg omdat gemeenteVariabelen
    // pas gefetched werd bij een wijziging op userSelectGemeente — niet
    // bij de impliciete single-gemeente auto-pick. Fix: na een
    // inGemeentenResponse-fetch ook gemeenteVariabelen + evenementen-
    // overlap fetchen.

    // Municipality::factory triggert de Observer die default-variabelen
    // seedt (incl. `aanwezigen=500`) zodat we 'm niet handmatig hoeven
    // toe te voegen — als 't toch nog niet bestaat (edge case in legacy
    // tests) zorgen we ervoor dat de waarde gezet is.
    $muni = Municipality::firstOrCreate(
        ['brk_identification' => 'GM0917'],
        ['name' => 'Heerlen'],
    );
    MunicipalityVariable::updateOrCreate(
        ['municipality_id' => $muni->id, 'key' => 'aanwezigen'],
        [
            'name' => 'Aanwezigen',
            'type' => MunicipalityVariableType::Number,
            'value' => 500,
            'is_default' => true,
        ],
    );

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    // Simuleer een succesvolle BAG-lookup met 1 gevonden gemeente —
    // FormDerivedState picked die automatisch als evenementInGemeente.
    $page->state()->setVariable('inGemeentenResponse', [
        'all' => ['items' => [
            ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
        ]],
    ]);

    // Trigger de fetch-keten via updated() — exact wat Livewire doet
    // als het adres-veld verandert.
    $page->updated('data.adresVanDeGebouwEn');

    // Bewijs: gemeenteVariabelen.aanwezigen is gevuld zodat het label
    // "Is het aantal aanwezigen minder dan {{ aanwezigen }} personen?"
    // de waarde 500 kan invullen.
    expect($page->state()->get('gemeenteVariabelen.aanwezigen'))->toBe(500.0);
});

test('a draft with a malformed datetime year mounts without crashing', function (string $badValue) {
    // Een native datetime-local input laat een organisator een jaartal met te
    // veel cijfers typen. Die waarde is in het concept opgeslagen en liet
    // Filaments datetime-cast crashen bij het heropenen (Sentry EVENTLOKET-10).
    $state = FormState::empty();
    $state->setField('EvenementStart', $badValue);
    // Ook een geneste repeater-waarde (het pad waar EVENTLOKET-10 vandaan kwam).
    $state->setField(
        'geefAanOpWelkeDataEnTijdenUDeVoorwerpenWiltPlaatsenOpDeOpenbareWegOfGroteVoertuigenWiltParkerenInDeBuurtVanHetEvenement',
        [['voorwerp' => 'kraam', 'startTijdstipVoorwerp' => $badValue]],
    );
    $this->draft->update(['state' => $state->toSnapshot()]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->assertOk();

    /** @var EventFormPage $page */
    $page = $component->instance();

    // De misvormde waarde is bij het laden opgeschoond naar null.
    expect($page->state()->get('EvenementStart'))->toBeNull();
})->with([
    'five digit year' => '20256-09-20T16:00',
    'six digit year' => '202026-08-22T13:00',
]);

test('gate: één gemeente uit een aangevuld adres → doorgelaten zonder tweede PDOK-call', function () {
    Municipality::firstOrCreate(['brk_identification' => 'GM0917'], ['name' => 'Heerlen']);
    Http::fake();

    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])->instance();
    $page->data['adresVanDeGebouwEn'] = [
        'row-1' => ['adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
            'postcode' => '6411AA', 'huisnummer' => '1', 'brkGemeente' => 'GM0917',
        ]],
    ];

    // Mag niet blokkeren: precies één gemeente bepaald.
    locatieGateCallback()($page);

    expect($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0917');
    Http::assertNothingSent();
});

test('gate: geen locatie-input → blokkeert (Halt) en geen gemeente bepaald', function () {
    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])->instance();

    expect(fn () => locatieGateCallback()($page))->toThrow(Halt::class);
    expect($page->state()->get('evenementInGemeente'))->toBeNull();
});

test('gate: twee gemeenten zonder keuze blokkeert en toont de keuze-radio; na keuze doorgelaten', function () {
    Municipality::firstOrCreate(['brk_identification' => 'GM0917'], ['name' => 'Heerlen']);
    Municipality::firstOrCreate(['brk_identification' => 'GM0935'], ['name' => 'Maastricht']);
    Http::fake();

    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])->instance();
    $page->data['adresVanDeGebouwEn'] = [
        'row-1' => ['adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
            'postcode' => '6411AA', 'huisnummer' => '1', 'brkGemeente' => 'GM0917',
        ]],
        'row-2' => ['adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
            'postcode' => '6211AA', 'huisnummer' => '1', 'brkGemeente' => 'GM0935',
        ]],
    ];

    // Meerdere gemeenten, geen keuze → blokkeren.
    expect(fn () => locatieGateCallback()($page))->toThrow(Halt::class);
    // De response staat wél in de state zodat de keuze-radio zichtbaar wordt.
    expect($page->state()->get('inGemeentenResponse.all.items'))->toHaveCount(2);

    // Nu een gemeente kiezen → doorgelaten.
    $page->data['userSelectGemeente'] = 'GM0935';
    locatieGateCallback()($page);

    expect($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0935');
    Http::assertNothingSent();
});

test('gate: een getekend vlak bepaalt de gemeente autoritatief (kaart blijft werken)', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM0999',
        'name' => 'Testgemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);

    /** @var EventFormPage $page */
    $page = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])->instance();
    $page->data['locatieSOpKaart'] = [
        'row-1' => [
            'naamVanDeLocatieKaart' => 'Plein',
            'buitenLocatieVanHetEvenement' => [
                'lat' => 0.0, 'lng' => 0.0,
                'geojson' => [
                    'type' => 'FeatureCollection',
                    'features' => [[
                        'type' => 'Feature',
                        'properties' => new stdClass,
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[-0.5, -0.5], [0.5, -0.5], [0.5, 0.5], [-0.5, 0.5], [-0.5, -0.5]]],
                        ],
                    ]],
                ],
            ],
        ],
    ];

    locatieGateCallback()($page);

    expect($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0999');
});

test('mount clears the "Mijn omgeving" placeholder left in an old draft of a personal organisation', function () {
    $this->organisation->update([
        'type' => OrganisationType::Personal,
        'name' => 'Mijn omgeving',
    ]);

    // Concept van vóór de fix: de placeholder staat al in de opgeslagen state.
    $this->draft->update([
        'state' => (new FormState(values: [
            'watIsDeNaamVanUwOrganisatie' => 'Mijn omgeving',
        ]))->toSnapshot(),
    ]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state()->get('watIsDeNaamVanUwOrganisatie'))->toBe('');
});

test('mount keeps the organisation name for a business organisation', function () {
    $this->organisation->update([
        'type' => OrganisationType::Business,
        'name' => 'Media Tuin',
        'coc_number' => '12345678',
    ]);

    $this->draft->update([
        'state' => (new FormState(values: [
            'watIsDeNaamVanUwOrganisatie' => 'Media Tuin',
        ]))->toSnapshot(),
    ]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state()->get('watIsDeNaamVanUwOrganisatie'))->toBe('Media Tuin');
});
