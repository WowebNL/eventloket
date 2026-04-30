<?php

declare(strict_types=1);

use App\Enums\MunicipalityVariableType;
use App\Enums\Role;
use App\EventForm\Persistence\Draft;
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
    Filament::setTenant($this->organisation);
});

test('the page mounts for an authenticated user', function () {
    Livewire::test(EventFormPage::class)
        ->assertOk();
});

test('mount seeds FormState with authUser and authOrganisation', function () {
    $component = Livewire::test(EventFormPage::class);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state())->toBeInstanceOf(FormState::class)
        ->and($page->state()->get('authUser'))->toBeInstanceOf(User::class)
        ->and($page->state()->get('authOrganisation'))->toBeInstanceOf(Organisation::class);
});

test('mount hydrates eventloketSession via ServiceFetcher', function () {
    $component = Livewire::test(EventFormPage::class);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state()->get('eventloketSession.user_uuid'))->toBe($this->user->uuid)
        ->and($page->state()->get('eventloketSession.organiser_uuid'))->toBe($this->organisation->uuid);
});

test('mount loads an existing draft if present for user + organisation', function () {
    // Gebruik een veld-key die GEEN session-prefill voor zich heeft (zie
    // EventFormPage::applySessionPrefill), zodat de prefill de draft-
    // waarde niet alsnog overschrijft.
    $state = FormState::empty();
    $state->setField('watIsDeNaamVanHetEvenementVergunning', 'Eva se feest');
    Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => $state->toSnapshot(),
        'current_step_key' => null,
    ]);

    $component = Livewire::test(EventFormPage::class);

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect($page->state()->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Eva se feest');
});

test('route die start+eindigt in dezelfde gemeente maar door ≥2 gemeenten gaat → eerdere gemeente-keuze wordt geleegd', function () {
    // Bug-rapport-equivalent uit OF: de organisator had al een
    // gemeente gekozen (Heerlen), tekent dan een route die start+eindigt
    // in Heerlen maar door Maastricht ook loopt. Heerlen-keuze hoort
    // dan opnieuw bevestigd te worden — anders blijft 't gebaseerd op
    // een outdated route-state. Migreert OF-rule
    // be547255-4a1b-4f37-96e8-919d5351e7a5.
    $component = Livewire::test(EventFormPage::class);

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
    $component = Livewire::test(EventFormPage::class);

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

    $muni = Municipality::firstOrCreate(
        ['brk_identification' => 'GM0917'],
        ['name' => 'Heerlen'],
    );
    MunicipalityVariable::factory()->create([
        'municipality_id' => $muni->id,
        'key' => 'aanwezigen',
        'value' => 500,
        'type' => MunicipalityVariableType::Number,
    ]);

    $component = Livewire::test(EventFormPage::class);

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
