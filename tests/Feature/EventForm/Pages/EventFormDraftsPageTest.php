<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\ZaakRelatieType;
use App\Enums\ZaaktypeRole;
use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\DraftStore;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\EventForm\State\FormState;
use App\Filament\Organiser\Pages\EventFormDraftsPage;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\MunicipalityFormQuestion;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\ZaakRelatie;
use App\Models\Zaaktype;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);
});

function maakConcept(User $user, Organisation $organisation, array $values = [], ?string $stepKey = null): Draft
{
    return Draft::create([
        'user_id' => $user->id,
        'organisation_id' => $organisation->id,
        'state' => ['values' => $values, 'system' => []],
        'name' => $values['watIsDeNaamVanHetEvenementVergunning'] ?? null,
        'current_step_key' => $stepKey,
    ]);
}

test('zonder concepten wordt direct een vers concept aangemaakt en doorgestuurd naar het formulier', function () {
    $this->get(EventFormDraftsPage::getUrl(['tenant' => $this->organisation]))
        ->assertRedirect();

    $draft = Draft::sole();
    expect($draft->user_id)->toBe($this->user->id)
        ->and($draft->organisation_id)->toBe($this->organisation->id);
});

test('met concepten toont het overzicht alleen eigen concepten binnen de organisatie', function () {
    $own = maakConcept($this->user, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => 'Eigen feest']);

    // Collega binnen dezelfde organisatie én een eigen concept in een
    // andere organisatie: beide horen onzichtbaar te zijn.
    $collega = User::factory()->create(['role' => Role::Organiser]);
    $collega->organisations()->attach($this->organisation->id, ['role' => 'admin']);
    $vanCollega = maakConcept($collega, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => 'Collega-feest']);

    $andereOrg = Organisation::factory()->create();
    $this->user->organisations()->attach($andereOrg->id, ['role' => 'admin']);
    $inAndereOrg = maakConcept($this->user, $andereOrg, ['watIsDeNaamVanHetEvenementVergunning' => 'Ander-org-feest']);

    Livewire::test(EventFormDraftsPage::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$vanCollega, $inAndereOrg]);
});

test('de voortgangskolom toont de stap-positie van het concept', function () {
    $draft = maakConcept($this->user, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => 'Feest'], TijdenStep::UUID);

    // Dit concept heeft geen aanvullende vragen, dus de wizard telt de stap
    // "Aanvullende vragen" niet mee.
    $uuids = EventFormSchema::stepUuidsInOrder(withAanvullendeVragen: false);
    $position = array_search(TijdenStep::UUID, $uuids, true) + 1;

    Livewire::test(EventFormDraftsPage::class)
        ->assertCanSeeTableRecords([$draft])
        ->assertSee('Stap '.$position.' van '.count($uuids));
});

test('de voortgangskolom telt de stap Aanvullende vragen mee zodra de gemeente er heeft', function () {
    $draft = maakConcept($this->user, $this->organisation, [
        'watIsDeNaamVanHetEvenementVergunning' => 'Feest',
        'gemeenteVariabelen' => [
            'extra_questions' => [
                ['id' => 1, 'type' => 'text', 'label' => 'Nog iets?', 'options' => []],
            ],
        ],
    ], TijdenStep::UUID);

    $uuids = EventFormSchema::stepUuidsInOrder(withAanvullendeVragen: true);
    $position = array_search(TijdenStep::UUID, $uuids, true) + 1;

    Livewire::test(EventFormDraftsPage::class)
        ->assertCanSeeTableRecords([$draft])
        ->assertSee('Stap '.$position.' van '.count($uuids));
});

test('de formulier-route bevat het draft-id als pad-segment', function () {
    $draft = maakConcept($this->user, $this->organisation, ['x' => 1]);

    expect(EventFormPage::getUrl(['draft' => $draft, 'tenant' => $this->organisation]))
        ->toEndWith("/aanvraag/{$draft->id}");
});

test('de verwijderen-actie verwijdert het concept', function () {
    $draft = maakConcept($this->user, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => 'Weg ermee']);
    // Tweede concept zodat mount() niet redirect na verwijdering.
    maakConcept($this->user, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => 'Blijft']);

    Livewire::test(EventFormDraftsPage::class)
        ->callAction(TestAction::make('verwijderen')->table($draft));

    expect(Draft::whereKey($draft->id)->exists())->toBeFalse();
});

test('start nieuwe aanvraag maakt een concept aan en stuurt door naar het formulier', function () {
    maakConcept($this->user, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => 'Bestaand']);

    Livewire::test(EventFormDraftsPage::class)
        ->callAction('startNieuweAanvraag')
        ->assertRedirect();

    expect(Draft::ownedBy($this->user, $this->organisation)->count())->toBe(2);
});

test('start nieuwe aanvraag blokkeert met een melding wanneer het maximum is bereikt', function () {
    foreach (range(1, DraftStore::MAX_DRAFTS) as $i) {
        maakConcept($this->user, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => "Concept {$i}"]);
    }

    Livewire::test(EventFormDraftsPage::class)
        ->callAction('startNieuweAanvraag')
        ->assertNotified('Maximum aantal concepten bereikt');

    expect(Draft::ownedBy($this->user, $this->organisation)->count())->toBe(DraftStore::MAX_DRAFTS);
});

function zaakMetSnapshotVoor(User $user, Organisation $organisation, array $values): Zaak
{
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'is_active' => true,
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
        'organiser_user_id' => $user->id,
        'form_state_snapshot' => ['values' => $values, 'system' => []],
    ]);
}

test('prefill_from_zaak zet de prefill in een nieuw concept zonder bestaande concepten te raken', function () {
    $bestaand = maakConcept($this->user, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => 'Bestaand concept']);
    $zaak = zaakMetSnapshotVoor($this->user, $this->organisation, [
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
    ]);

    $component = Livewire::withQueryParams(['prefill_from_zaak' => $zaak->id])
        ->test(EventFormDraftsPage::class);

    $nieuw = Draft::ownedBy($this->user, $this->organisation)
        ->whereKeyNot($bestaand->id)
        ->sole();

    // De redirect draagt `bron=hergebruik` zodat het formulier de
    // hergebruik-melding toont i.p.v. "Concept hervat".
    $component->assertRedirect(EventFormPage::getUrl(['draft' => $nieuw, 'bron' => 'hergebruik']));

    expect($nieuw->name)->toBe('Buurtfeest 2027')
        ->and(FormState::fromSnapshot($bestaand->refresh()->state)->get('watIsDeNaamVanHetEvenementVergunning'))
        ->toBe('Bestaand concept');
});

test('prefill_from_zaak blokkeert met een melding wanneer het maximum is bereikt', function () {
    foreach (range(1, DraftStore::MAX_DRAFTS) as $i) {
        maakConcept($this->user, $this->organisation, ['watIsDeNaamVanHetEvenementVergunning' => "Concept {$i}"]);
    }
    $zaak = zaakMetSnapshotVoor($this->user, $this->organisation, [
        'watIsDeNaamVanHetEvenementVergunning' => 'Past er niet meer bij',
    ]);

    Livewire::withQueryParams(['prefill_from_zaak' => $zaak->id])
        ->test(EventFormDraftsPage::class)
        ->assertNotified('Hergebruik niet mogelijk');

    expect(Draft::ownedBy($this->user, $this->organisation)->count())->toBe(DraftStore::MAX_DRAFTS);
});

/**
 * Issue #10: "Definitieve aanvraag indienen" on a vooraankondiging. The
 * prefill flow detects that the source zaak is a vooraankondiging, flips
 * the form to the regular aanvraag flow, presets the link fields and
 * records the origin on the concept.
 */
function vooraankondigingMetSnapshotVoor(User $user, Organisation $organisation, array $values): Zaak
{
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Vooraankondiging gemeente Test',
        'is_active' => true,
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
        'organiser_user_id' => $user->id,
        'form_state_snapshot' => ['values' => $values, 'system' => []],
    ]);
}

test('prefill_from_zaak legt de bron-zaak vast op het nieuwe concept', function () {
    $zaak = zaakMetSnapshotVoor($this->user, $this->organisation, [
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
    ]);

    Livewire::withQueryParams(['prefill_from_zaak' => $zaak->id])
        ->test(EventFormDraftsPage::class);

    expect(Draft::ownedBy($this->user, $this->organisation)->sole()->source_zaak_id)->toBe($zaak->id);
});

test('prefill vanaf een vooraankondiging zet de omzettings-presets in het concept', function () {
    $vooraankondiging = vooraankondigingMetSnapshotVoor($this->user, $this->organisation, [
        'watIsDeNaamVanHetEvenementVergunning' => 'Kermis 2027',
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
    ]);

    Livewire::withQueryParams(['prefill_from_zaak' => $vooraankondiging->id])
        ->test(EventFormDraftsPage::class);

    $draft = Draft::ownedBy($this->user, $this->organisation)->sole();
    $state = FormState::fromSnapshot($draft->state);

    expect($draft->source_zaak_id)->toBe($vooraankondiging->id)
        // The copied snapshot said "vooraankondiging"; without this flip
        // the organiser would file a second vooraankondiging instead of
        // the definitive aanvraag.
        ->and($state->get('waarvoorWiltUEventloketGebruiken'))->toBe('evenement')
        ->and($state->get(Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD))->toBe('Ja')
        ->and($state->get(Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD))->toBe($vooraankondiging->id)
        ->and($state->get(Vragenboom2Step::VOORAANKONDIGING_ZAAKNUMMER_FIELD))->toBe($vooraankondiging->public_id);
});

test('prefill vanaf een gewone zaak zet geen omzettings-presets', function () {
    $zaak = zaakMetSnapshotVoor($this->user, $this->organisation, [
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
    ]);

    Livewire::withQueryParams(['prefill_from_zaak' => $zaak->id])
        ->test(EventFormDraftsPage::class);

    $state = FormState::fromSnapshot(Draft::ownedBy($this->user, $this->organisation)->sole()->state);

    expect($state->get(Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD))->toBeNull()
        ->and($state->get(Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD))->toBeNull();
});

test('prefill vanaf een via de koppeling herkende vooraankondiging zet de omzettings-presets', function () {
    // Same conversion, but for a municipality on its own ZGW instance: the
    // zaaktype name is the external omschrijving, so only the koppeling
    // identifies the vooraankondiging.
    $municipality = Municipality::factory()->create();

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vooraankondiging,
        'zaaktype_identificatie' => 'EXT-1',
    ]);

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'identificatie' => 'EXT-1',
        'name' => 'Activiteit behandelen',
        'role' => ZaaktypeRole::Vooraankondiging,
        'is_active' => true,
    ]);

    $vooraankondiging = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'organiser_user_id' => $this->user->id,
        'form_state_snapshot' => ['values' => [
            'watIsDeNaamVanHetEvenementVergunning' => 'Kermis 2027',
            'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
        ], 'system' => []],
    ]);

    Livewire::withQueryParams(['prefill_from_zaak' => $vooraankondiging->id])
        ->test(EventFormDraftsPage::class);

    $state = FormState::fromSnapshot(Draft::ownedBy($this->user, $this->organisation)->sole()->state);

    expect($state->get('waarvoorWiltUEventloketGebruiken'))->toBe('evenement')
        ->and($state->get(Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD))->toBe('Ja')
        ->and($state->get(Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD))->toBe($vooraankondiging->id);
});

/**
 * The answers to the municipality's own extra questions. The generic prefill
 * drops them, but converting a vooraankondiging into the definitive aanvraag
 * keeps the event at the same municipality, so they are carried over.
 */
function vooraankondigingMetEigenVragen(User $user, Organisation $organisation, callable $questions): array
{
    $vooraankondiging = vooraankondigingMetSnapshotVoor($user, $organisation, []);
    $municipality = $vooraankondiging->municipality;

    $made = $questions($municipality);

    return [$vooraankondiging, $municipality, $made];
}

function zetSnapshot(Zaak $zaak, array $values): void
{
    $zaak->update(['form_state_snapshot' => ['values' => [
        'watIsDeNaamVanHetEvenementVergunning' => 'Kermis 2027',
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
        ...$values,
    ], 'system' => []]]);
}

function conceptStateNaPrefill(Zaak $zaak, User $user, Organisation $organisation): FormState
{
    Livewire::withQueryParams(['prefill_from_zaak' => $zaak->id])
        ->test(EventFormDraftsPage::class);

    return FormState::fromSnapshot(Draft::ownedBy($user, $organisation)->sole()->state);
}

test('omzetten van een vooraankondiging neemt de antwoorden op de eigen vragen mee', function () {
    [$vooraankondiging, , $q] = vooraankondigingMetEigenVragen($this->user, $this->organisation, fn (Municipality $m): array => [
        'tekst' => MunicipalityFormQuestion::factory()->for($m)->create(['order' => 1]),
        'radio' => MunicipalityFormQuestion::factory()->for($m)->radio(['Ja', 'Nee'])->create(['order' => 2]),
        'vinkjes' => MunicipalityFormQuestion::factory()->for($m)->checkboxes(['Podium', 'Tent', 'Kraam'])->create(['order' => 3]),
        'beidePaden' => MunicipalityFormQuestion::factory()->for($m)
            ->forAanvraagTypes([ZaaktypeRole::Vooraankondiging->value, ZaaktypeRole::Vergunning->value])->create(['order' => 4]),
    ]);

    zetSnapshot($vooraankondiging, [
        "extraVraag_{$q['tekst']->id}" => 'Een springkussen',
        "extraVraag_{$q['radio']->id}" => 'Ja',
        "extraVraag_{$q['vinkjes']->id}" => ['Podium', 'Kraam'],
        "extraVraag_{$q['beidePaden']->id}" => 'Op beide paden',
    ]);

    $state = conceptStateNaPrefill($vooraankondiging, $this->user, $this->organisation);

    expect($state->get("extraVraag_{$q['tekst']->id}"))->toBe('Een springkussen')
        ->and($state->get("extraVraag_{$q['radio']->id}"))->toBe('Ja')
        ->and($state->get("extraVraag_{$q['vinkjes']->id}"))->toBe(['Podium', 'Kraam'])
        ->and($state->get("extraVraag_{$q['beidePaden']->id}"))->toBe('Op beide paden')
        // The other presets of the conversion still apply.
        ->and($state->get('waarvoorWiltUEventloketGebruiken'))->toBe('evenement')
        ->and($state->get(Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD))->toBe($vooraankondiging->id);
});

test('omzetten neemt geen antwoord mee op een vraag die niet op het vergunningpad staat', function () {
    [$vooraankondiging, , $q] = vooraankondigingMetEigenVragen($this->user, $this->organisation, fn (Municipality $m): array => [
        'alleenVooraankondiging' => MunicipalityFormQuestion::factory()->for($m)
            ->forAanvraagTypes([ZaaktypeRole::Vooraankondiging->value])->create(['order' => 1]),
        'alleenMelding' => MunicipalityFormQuestion::factory()->for($m)
            ->forAanvraagTypes([ZaaktypeRole::Melding->value])->create(['order' => 2]),
    ]);

    zetSnapshot($vooraankondiging, [
        "extraVraag_{$q['alleenVooraankondiging']->id}" => 'Alleen bij de vooraankondiging',
        "extraVraag_{$q['alleenMelding']->id}" => 'Alleen bij een melding',
    ]);

    $state = conceptStateNaPrefill($vooraankondiging, $this->user, $this->organisation);

    expect($state->get("extraVraag_{$q['alleenVooraankondiging']->id}"))->toBeNull()
        ->and($state->get("extraVraag_{$q['alleenMelding']->id}"))->toBeNull();
});

test('omzetten neemt geen antwoord mee op een vraag die de gemeente niet meer actief heeft', function () {
    [$vooraankondiging, , $q] = vooraankondigingMetEigenVragen($this->user, $this->organisation, fn (Municipality $m): array => [
        'inactief' => MunicipalityFormQuestion::factory()->for($m)->inactive()->create(['order' => 1]),
    ]);

    zetSnapshot($vooraankondiging, [
        "extraVraag_{$q['inactief']->id}" => 'Een vraag die uit staat',
    ]);

    $state = conceptStateNaPrefill($vooraankondiging, $this->user, $this->organisation);

    expect($state->get("extraVraag_{$q['inactief']->id}"))->toBeNull();
});

test('omzetten neemt geen antwoord mee op een vraag van een andere gemeente', function () {
    [$vooraankondiging] = vooraankondigingMetEigenVragen($this->user, $this->organisation, fn (): array => []);
    $vanElders = MunicipalityFormQuestion::factory()->for(Municipality::factory())->create(['order' => 1]);

    zetSnapshot($vooraankondiging, [
        "extraVraag_{$vanElders->id}" => 'Hoort bij een andere gemeente',
    ]);

    $state = conceptStateNaPrefill($vooraankondiging, $this->user, $this->organisation);

    expect($state->get("extraVraag_{$vanElders->id}"))->toBeNull();
});

test('omzetten laat keuzes vallen die de vraag niet meer aanbiedt', function () {
    [$vooraankondiging, , $q] = vooraankondigingMetEigenVragen($this->user, $this->organisation, fn (Municipality $m): array => [
        'radio' => MunicipalityFormQuestion::factory()->for($m)->radio(['Ja', 'Nee'])->create(['order' => 1]),
        'vinkjes' => MunicipalityFormQuestion::factory()->for($m)->checkboxes(['Podium', 'Tent'])->create(['order' => 2]),
        'vinkjesWeg' => MunicipalityFormQuestion::factory()->for($m)->checkboxes(['Podium', 'Tent'])->create(['order' => 3]),
        'leeg' => MunicipalityFormQuestion::factory()->for($m)->create(['order' => 4]),
    ]);

    zetSnapshot($vooraankondiging, [
        "extraVraag_{$q['radio']->id}" => 'Misschien',
        "extraVraag_{$q['vinkjes']->id}" => ['Podium', 'Kraam'],
        "extraVraag_{$q['vinkjesWeg']->id}" => ['Kraam'],
        "extraVraag_{$q['leeg']->id}" => '   ',
    ]);

    $state = conceptStateNaPrefill($vooraankondiging, $this->user, $this->organisation);

    expect($state->get("extraVraag_{$q['radio']->id}"))->toBeNull()
        ->and($state->get("extraVraag_{$q['vinkjes']->id}"))->toBe(['Podium'])
        ->and($state->get("extraVraag_{$q['vinkjesWeg']->id}"))->toBeNull()
        ->and($state->get("extraVraag_{$q['leeg']->id}"))->toBeNull();
});

test('herhalen van een gewone aanvraag neemt de antwoorden op de eigen vragen nog steeds niet mee', function () {
    $zaak = zaakMetSnapshotVoor($this->user, $this->organisation, []);
    $vraag = MunicipalityFormQuestion::factory()->for($zaak->municipality)->create(['order' => 1]);
    $zaak->update(['form_state_snapshot' => ['values' => [
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        "extraVraag_{$vraag->id}" => 'Een springkussen',
    ], 'system' => []]]);

    $state = conceptStateNaPrefill($zaak, $this->user, $this->organisation);

    expect($state->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Buurtfeest 2027')
        ->and($state->get("extraVraag_{$vraag->id}"))->toBeNull();
});

test('een vooraankondiging die al is opgevolgd neemt bij herhalen geen antwoorden op de eigen vragen mee', function () {
    [$vooraankondiging, , $q] = vooraankondigingMetEigenVragen($this->user, $this->organisation, fn (Municipality $m): array => [
        'tekst' => MunicipalityFormQuestion::factory()->for($m)->create(['order' => 1]),
    ]);
    zetSnapshot($vooraankondiging, ["extraVraag_{$q['tekst']->id}" => 'Een springkussen']);

    $opvolger = zaakMetSnapshotVoor($this->user, $this->organisation, []);
    ZaakRelatie::create([
        'zaak_id' => $opvolger->id,
        'gerelateerde_zaak_id' => $vooraankondiging->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);

    $state = conceptStateNaPrefill($vooraankondiging, $this->user, $this->organisation);

    expect($state->get("extraVraag_{$q['tekst']->id}"))->toBeNull();
});
