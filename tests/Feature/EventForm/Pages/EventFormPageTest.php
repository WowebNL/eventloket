<?php

declare(strict_types=1);

use App\Enums\MunicipalityVariableType;
use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\State\FormState;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

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
