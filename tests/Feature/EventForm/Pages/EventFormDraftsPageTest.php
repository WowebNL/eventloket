<?php

declare(strict_types=1);

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\DraftStore;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\State\FormState;
use App\Filament\Organiser\Pages\EventFormDraftsPage;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
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

    $position = array_search(TijdenStep::UUID, EventFormSchema::stepUuidsInOrder(), true) + 1;
    $total = count(EventFormSchema::stepUuidsInOrder());

    Livewire::test(EventFormDraftsPage::class)
        ->assertCanSeeTableRecords([$draft])
        ->assertSee("Stap {$position} van {$total}");
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
        ->and($state->get(\App\EventForm\Schema\Steps\Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD))->toBe('Ja')
        ->and($state->get(\App\EventForm\Schema\Steps\Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD))->toBe($vooraankondiging->id)
        ->and($state->get(\App\EventForm\Schema\Steps\Vragenboom2Step::VOORAANKONDIGING_ZAAKNUMMER_FIELD))->toBe($vooraankondiging->public_id);
});

test('prefill vanaf een gewone zaak zet geen omzettings-presets', function () {
    $zaak = zaakMetSnapshotVoor($this->user, $this->organisation, [
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
    ]);

    Livewire::withQueryParams(['prefill_from_zaak' => $zaak->id])
        ->test(EventFormDraftsPage::class);

    $state = FormState::fromSnapshot(Draft::ownedBy($this->user, $this->organisation)->sole()->state);

    expect($state->get(\App\EventForm\Schema\Steps\Vragenboom2Step::HEEFT_VOORAANKONDIGING_FIELD))->toBeNull()
        ->and($state->get(\App\EventForm\Schema\Steps\Vragenboom2Step::VOORAANKONDIGING_ZAAK_FIELD))->toBeNull();
});
