<?php

declare(strict_types=1);

/**
 * The aanvraagtype in the zaken overviews.
 *
 * When several roles share one zaaktype, the zaaktype says nothing about the
 * kind of aanvraag any more. The overviews therefore show, search and filter
 * the role of the zaak itself, labelled as the aanvraagtype.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Organiser\Widgets\OrganiserCalendarWidget;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

/**
 * @param  list<ZaaktypeRole>  $roles  koppeld to one zaaktype, in this order
 * @return array<string, Zaak> role value => zaak
 */
function overzichtZakenOpGedeeldZaaktype(Municipality $municipality, Organisation $organisation, array $roles): array
{
    foreach ($roles as $role) {
        MunicipalityZaaktypeMapping::create([
            'municipality_id' => $municipality->id,
            'role' => $role,
            'zaaktype_identificatie' => 'SYN-GEDEELD',
        ]);
    }

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'identificatie' => 'SYN-GEDEELD',
        'name' => 'Evenement behandelen',
        'role' => null,
        'is_active' => true,
    ]);

    $zaken = [];
    foreach ($roles as $role) {
        $zaken[$role->value] = Zaak::factory()->create([
            'zaaktype_id' => $zaaktype->id,
            'role' => $role,
            'organisation_id' => $organisation->id,
            'reference_data' => new ZaakReferenceData(
                registratiedatum: now()->toIso8601String(),
                status_name: 'Ontvangen',
                statustype_url: '',
                start_evenement: now()->addDay()->toIso8601String(),
                eind_evenement: now()->addDays(2)->toIso8601String(),
                naam_evenement: "Synthetisch {$role->value}",
            ),
        ]);
    }

    return $zaken;
}

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    ZgwHttpFake::wildcardFake();

    $this->municipality = Municipality::factory()->create(['name' => 'Synthetische gemeente']);
    $this->organisation = Organisation::factory()->create();

    // Vergunning first: anything that falls back to the zaaktype lands on it.
    $this->zaken = overzichtZakenOpGedeeldZaaktype($this->municipality, $this->organisation, [
        ZaaktypeRole::Vergunning,
        ZaaktypeRole::Melding,
    ]);
});

it('shows the aanvraagtype of each zaak in the zaken list', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(ListZaken::class)
        ->assertTableColumnStateSet('role', ZaaktypeRole::Melding, $this->zaken['melding'])
        ->assertTableColumnStateSet('role', ZaaktypeRole::Vergunning, $this->zaken['vergunning']);
});

it('finds zaken by their aanvraagtype when the zaaktype is shared', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(ListZaken::class)
        ->searchTable('meld')
        ->assertCanSeeTableRecords([$this->zaken['melding']])
        ->assertCanNotSeeTableRecords([$this->zaken['vergunning']]);
});

it('sorts the zaken list on the aanvraagtype', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(ListZaken::class)
        ->sortTable('role')
        ->assertCanSeeTableRecords([$this->zaken['melding'], $this->zaken['vergunning']], inOrder: true);
});

it('labels the role filter as the aanvraagtype', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(ListZaken::class)
        ->assertTableFilterExists('role', fn ($filter): bool => $filter->getLabel() === 'Aanvraagtype');
});

it('lets the organiser filter the calendar on the aanvraagtype', function () {
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin]);

    $this->actingAs($organiser);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    livewire(OrganiserCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->callAction('filter', data: ['zaaktype_roles' => [ZaaktypeRole::Melding->value]])
        ->assertCanSeeTableRecords([$this->zaken['melding']])
        ->assertCanNotSeeTableRecords([$this->zaken['vergunning']]);
});

it('hides the zaaktype column by default but keeps it available', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(ListZaken::class)
        ->assertTableColumnExists('zaaktype.name')
        ->assertCanNotRenderTableColumn('zaaktype.name')
        ->assertCanRenderTableColumn('role');
});
