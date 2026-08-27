<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak as OrganiserViewZaak;
use App\Filament\Organiser\Widgets\OrganiserCalendarWidget;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\Schemas\ZaakInfolist;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->organisation = Organisation::factory()->create([
        'name' => 'Testorganisatie BV',
        'coc_number' => '12345678',
        'phone' => '0600000011',
        'email' => 'eigen-org@voorbeeld.test',
    ]);

    $this->organiser = User::factory()->create([
        'role' => Role::Organiser,
        'first_name' => 'Test',
        'last_name' => 'Indiener',
    ]);
    $this->organisation->users()->attach($this->organiser, ['role' => OrganisationRole::Admin->value]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'organiser_user_id' => $this->organiser->id,
        'zgw_zaak_url' => null,
    ]);
});

/**
 * Acting-as helper that mirrors production auth: the guard retrieves the user
 * through the model builder, which resolves the role-specific child class
 * (OrganiserUser, MunicipalityUser, ...). A plain factory instance is the
 * base User, so instanceof checks in the visibility closures would miss.
 */
function actingAsResolved(int $userId): User
{
    $user = User::find($userId);
    test()->actingAs($user);

    return $user;
}

// --- canSeeCaseParties: the gate behind the four fields (decision D) ---

test('canSeeCaseParties allows a case handler', function () {
    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    actingAsResolved($admin->id);

    expect(ZaakInfolist::canSeeCaseParties($this->zaak))->toBeTrue();
});

test('canSeeCaseParties allows an organiser on a case of their own organisation', function () {
    actingAsResolved($this->organiser->id);

    expect(ZaakInfolist::canSeeCaseParties($this->zaak))->toBeTrue();
});

test('canSeeCaseParties hides the parties from an organiser of another organisation', function () {
    $otherOrganiser = User::factory()->create(['role' => Role::Organiser]);
    $otherOrg = Organisation::factory()->create();
    $otherOrg->users()->attach($otherOrganiser, ['role' => OrganisationRole::Admin->value]);

    actingAsResolved($otherOrganiser->id);

    expect(ZaakInfolist::canSeeCaseParties($this->zaak))->toBeFalse();
});

// --- Decision D: the organiser sees the parties on their own case ---

test('the organiser sees the case parties on a case of their own organisation', function () {
    actingAsResolved($this->organiser->id);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    livewire(OrganiserViewZaak::class, ['record' => $this->zaak->id])
        ->assertOk()
        ->assertSee('Naam indiener')
        ->assertSee('Test Indiener')
        ->assertSee('Naam organisatie')
        ->assertSee('Testorganisatie BV')
        ->assertSee('KVK-nummer van de organisatie')
        ->assertSee('12345678')
        // The contact fields carry the same gate and stay visible on the
        // organiser's own-organisation case.
        ->assertSee('0600000011')
        ->assertSee('eigen-org@voorbeeld.test');
});

// --- Decision D: another organisation's case leaks nothing on the calendar ---

test('an organiser does not see the case parties of another organisation on the calendar', function () {
    $otherOrg = Organisation::factory()->create([
        'name' => 'Andere Organisatie BV',
        'coc_number' => '87654321',
        'phone' => '0600000022',
        'email' => 'andere-org@voorbeeld.test',
    ]);
    $otherSubmitter = User::factory()->create([
        'role' => Role::Organiser,
        'first_name' => 'Andere',
        'last_name' => 'Persoon',
    ]);
    $otherOrg->users()->attach($otherSubmitter, ['role' => OrganisationRole::Admin->value]);
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $otherOrg->id,
        'organiser_user_id' => $otherSubmitter->id,
        'zgw_zaak_url' => null,
    ]);

    actingAsResolved($this->organiser->id);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    // The organiser calendar is not scoped to a single organisation, so the
    // other organisation's event is listed. The party fields must stay hidden.
    livewire(OrganiserCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertCanSeeTableRecords([$otherZaak])
        ->mountTableAction('view', $otherZaak)
        ->assertOk()
        ->assertDontSee('Andere Persoon')
        ->assertDontSee('Andere Organisatie BV')
        ->assertDontSee('87654321')
        // Contact fields of the other organisation stay hidden as well.
        ->assertDontSee('0600000022')
        ->assertDontSee('andere-org@voorbeeld.test');
});

// --- Case handler behaviour is unchanged (regression guard) ---

test('a case handler still sees the case parties', function () {
    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($admin);
    actingAsResolved($admin->id);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    livewire(ViewZaak::class, ['record' => $this->zaak->id])
        ->assertOk()
        ->assertSee('Naam indiener')
        ->assertSee('Test Indiener')
        ->assertSee('Naam organisatie')
        ->assertSee('Testorganisatie BV')
        ->assertSee('KVK-nummer van de organisatie')
        ->assertSee('12345678')
        ->assertSee('0600000011')
        ->assertSee('eigen-org@voorbeeld.test');
});

// --- Decision B: the "Mijn omgeving" placeholder is suppressed ---

test('the personal environment placeholder name is not shown to a case handler', function () {
    $personalOrg = Organisation::factory()->personal()->create();
    $personalUser = User::factory()->create(['role' => Role::Organiser, 'first_name' => 'Test', 'last_name' => 'Indiener']);
    $personalOrg->users()->attach($personalUser, ['role' => OrganisationRole::Admin->value]);
    $personalZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $personalOrg->id,
        'organiser_user_id' => $personalUser->id,
        'zgw_zaak_url' => null,
    ]);

    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($admin);
    actingAsResolved($admin->id);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    livewire(ViewZaak::class, ['record' => $personalZaak->id])
        ->assertOk()
        // The submitter's own name is still shown, only the organisation
        // name row is suppressed for a personal environment. Asserting on the
        // label rather than the value avoids matching the record snapshot that
        // Livewire embeds in the component payload.
        ->assertSee('Naam indiener')
        ->assertDontSee('Naam organisatie');
});

// --- Decision E: the submitter field is labelled "Naam indiener" ---

test('the submitter name field uses the indiener label, not organisator', function () {
    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($admin);
    actingAsResolved($admin->id);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    livewire(ViewZaak::class, ['record' => $this->zaak->id])
        ->assertOk()
        ->assertSee('Naam indiener')
        ->assertDontSee('Naam organisator');
});

// --- Calendar export is closed for the organiser (AVG) ---

test('the organiser calendar offers no export action', function () {
    actingAsResolved($this->organiser->id);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    livewire(OrganiserCalendarWidget::class)
        ->assertActionHidden('export');
});

test('a municipality calendar keeps its export action', function () {
    $admin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($admin);
    actingAsResolved($admin->id);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    livewire(MunicipalityCalendarWidget::class)
        ->assertActionVisible('export');
});
