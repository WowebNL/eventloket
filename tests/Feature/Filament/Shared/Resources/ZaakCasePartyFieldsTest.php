<?php

use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak as OrganiserViewZaak;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Advisory;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportTesting\Testable;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->municipality = Municipality::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->organisation = Organisation::factory()->create([
        'type' => 'business',
        'name' => 'Stichting Testevenementen',
        'coc_number' => '87654321',
    ]);

    // `name` is derived from first_name/last_name on the User model.
    $this->organiserUser = User::factory()->create([
        'first_name' => 'Testorganisator',
        'last_name' => 'Voorbeeld',
        'role' => Role::Organiser,
    ]);

    $this->organisation->users()->attach($this->organiserUser, [
        'role' => OrganisationRole::Admin,
    ]);

    // A zaakobject of type "adres" with this exact relatieomschrijving is what
    // `OzZaak::setZaakAddresses()` reads the event address from.
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'zaakobjecten' => [
                [
                    'objectType' => 'adres',
                    'relatieomschrijving' => 'Adres van het evenement',
                    'objectIdentificatie' => [
                        'postcode' => '1234AB',
                        'huisnummer' => 5,
                        'huisletter' => '',
                        'huisnummertoevoeging' => '',
                        'wplWoonplaatsNaam' => 'Testerdam',
                    ],
                ],
            ],
        ],
    ]);

    // A zaak without any address zaakobject, used for the empty-value case.
    $emptyZgwZaakUrl = ZgwHttpFake::fakeSingleZaak('2');

    ZgwHttpFake::wildcardFake();

    $this->emptyOrganisation = Organisation::factory()->create([
        'type' => 'personal',
        'name' => '',
        'coc_number' => null,
    ]);

    $this->zaakWithoutCaseParties = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->emptyOrganisation->id,
        'organiser_user_id' => null,
        'zgw_zaak_url' => $emptyZgwZaakUrl,
        'imported_data' => null,
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
        ),
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'organiser_user_id' => $this->organiserUser->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'imported_data' => null,
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
        ),
    ]);
});

function casePartyLabels(): array
{
    return [
        __('municipality/resources/zaak.columns.naam_organisator.label'),
        __('municipality/resources/zaak.columns.naam_organisatie.label'),
        __('municipality/resources/zaak.columns.adres_evenement.label'),
        __('municipality/resources/zaak.columns.kvk_nummer_organisatie.label'),
    ];
}

function assertSeesCasePartyFields(Testable $component): void
{
    $component->assertOk();

    foreach (casePartyLabels() as $label) {
        $component->assertSee($label);
    }

    $component
        ->assertSee('Testorganisator Voorbeeld')
        ->assertSee('Stichting Testevenementen')
        ->assertSee('87654321')
        ->assertSee('1234AB')
        ->assertSee('Testerdam');
}

test('coordinator sees the case party fields in the zaak infolist', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $coordinator = User::factory()->create(['role' => Role::Coordinator]);
    $coordinator->municipalities()->attach($this->municipality);

    $this->actingAs($coordinator);
    Filament::setTenant($this->municipality);

    assertSeesCasePartyFields(livewire(ViewZaak::class, ['record' => $this->zaak->id]));
});

test('reviewer sees the case party fields in the zaak infolist', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $reviewer->municipalities()->attach($this->municipality);

    $this->actingAs($reviewer);
    Filament::setTenant($this->municipality);

    assertSeesCasePartyFields(livewire(ViewZaak::class, ['record' => $this->zaak->id]));
});

test('advisor sees the case party fields in the zaak infolist', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));

    $advisory = Advisory::factory()->create(['can_view_any_zaak' => true]);
    $advisor = User::factory()->create(['role' => Role::Advisor]);
    $advisory->users()->attach($advisor, ['role' => AdvisoryRole::Member]);

    $this->actingAs($advisor);
    Filament::setTenant($advisory);

    assertSeesCasePartyFields(livewire(ViewZaak::class, ['record' => $this->zaak->id]));
});

test('admin sees the case party fields in the zaak infolist', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin);

    assertSeesCasePartyFields(livewire(ViewZaak::class, ['record' => $this->zaak->id]));
});

test('organiser does not see the case party fields in the zaak infolist', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $this->actingAs($this->organiserUser);
    Filament::setTenant($this->organisation);

    $component = livewire(OrganiserViewZaak::class, ['record' => $this->zaak->id])->assertOk();

    foreach (casePartyLabels() as $label) {
        $component->assertDontSee($label);
    }
});

test('case party fields are hidden when the underlying data is empty', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    $component = livewire(ViewZaak::class, ['record' => $this->zaakWithoutCaseParties->id])->assertOk();

    foreach (casePartyLabels() as $label) {
        $component->assertDontSee($label);
    }
});
