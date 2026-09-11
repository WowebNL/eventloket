<?php

declare(strict_types=1);

/**
 * The zaaktype-role filter on the zaken list.
 *
 * `zaaktypen.role` is nullable and is only written by a koppeling or by the
 * catalogus sync, so a row that predates the column keeps a null role until
 * one of those runs. Comparing that column directly makes every such zaak
 * invisible to the filter, because an `in` never matches a null. The list that
 * comes back then looks ordinary while being silently short, which is worse
 * for a reviewer than an empty one. The filter therefore has to read the role
 * through the same ladder {@see Zaaktype::effectiveRole()} uses.
 *
 * These tests deliberately leave `role` null, the way the factory does and the
 * way an upgraded database does. A test that fills the column first proves
 * only that the happy state works.
 */

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

/**
 * A zaak on a zaaktype of $municipality. The role column stays null unless a
 * test says otherwise, mirroring both the factory default and the state of a
 * database that was upgraded before the column existed.
 */
function zaakOpZaaktypeGenaamd(
    Municipality $municipality,
    Organisation $organisation,
    string $name,
    ?ZaaktypeRole $role = null,
): Zaak {
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => $name,
        'role' => $role,
        'is_active' => true,
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
    ]);
}

beforeEach(function (): void {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    ZgwHttpFake::wildcardFake();

    $this->municipality = Municipality::factory()->create(['name' => 'Testgemeente']);
    $this->organisation = Organisation::factory()->create();

    $this->zaakOp = fn (string $name, ?ZaaktypeRole $role = null, ?Municipality $municipality = null): Zaak => zaakOpZaaktypeGenaamd(
        $municipality ?? $this->municipality,
        $this->organisation,
        $name,
        $role,
    );

    $this->asPlatformAdmin = function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
    };
});

it('finds zaken whose zaaktype has no stored role but follows the catalogus naming convention', function () {
    $vergunning = ($this->zaakOp)('Evenementenvergunning Testgemeente');
    $melding = ($this->zaakOp)('Melding klein evenement Testgemeente');

    ($this->asPlatformAdmin)();

    livewire(ListZaken::class)
        ->assertCanSeeTableRecords([$vergunning, $melding])
        ->filterTable('role', [ZaaktypeRole::Vergunning])
        ->assertCanSeeTableRecords([$vergunning])
        ->assertCanNotSeeTableRecords([$melding]);
});

it('finds zaken whose role comes from a koppeling while the column is still empty', function () {
    // First rung of the ladder: a municipality on its own ZGW instance koppelt
    // the role, and the local row carries the external omschrijving, so neither
    // the column nor the name can tell the role.
    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EXT-1',
    ]);

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'identificatie' => 'EXT-1',
        'name' => 'Activiteit behandelen',
        'role' => null,
        'is_active' => true,
    ]);

    $gekoppeld = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    $melding = ($this->zaakOp)('Melding klein evenement Testgemeente');

    ($this->asPlatformAdmin)();

    livewire(ListZaken::class)
        ->filterTable('role', [ZaaktypeRole::Vergunning])
        ->assertCanSeeTableRecords([$gekoppeld])
        ->assertCanNotSeeTableRecords([$melding]);
});

it('keeps out a zaaktype whose stored role is not the one that was asked for', function () {
    // The column still wins over the name for rows without a koppeling, so the
    // ladder must not widen the filter either.
    $storedMelding = ($this->zaakOp)('Evenementenvergunning Testgemeente', ZaaktypeRole::Melding);
    $storedVergunning = ($this->zaakOp)('Activiteit behandelen', ZaaktypeRole::Vergunning);

    ($this->asPlatformAdmin)();

    livewire(ListZaken::class)
        ->filterTable('role', [ZaaktypeRole::Vergunning])
        ->assertCanSeeTableRecords([$storedVergunning])
        ->assertCanNotSeeTableRecords([$storedMelding]);
});

it('keeps out a zaaktype that resolves to no role at all', function () {
    $vergunning = ($this->zaakOp)('Evenementenvergunning Testgemeente');
    $roleless = ($this->zaakOp)('Naam zonder conventie');

    ($this->asPlatformAdmin)();

    livewire(ListZaken::class)
        ->filterTable('role', [ZaaktypeRole::Vergunning])
        ->assertCanSeeTableRecords([$vergunning])
        ->assertCanNotSeeTableRecords([$roleless]);
});

it('combines the roles that were selected and nothing else', function () {
    $vergunning = ($this->zaakOp)('Evenementenvergunning Testgemeente');
    $melding = ($this->zaakOp)('Melding klein evenement Testgemeente');
    $vooraankondiging = ($this->zaakOp)('Vooraankondiging Testgemeente');

    ($this->asPlatformAdmin)();

    livewire(ListZaken::class)
        ->filterTable('role', [ZaaktypeRole::Vergunning, ZaaktypeRole::Melding])
        ->assertCanSeeTableRecords([$vergunning, $melding])
        ->assertCanNotSeeTableRecords([$vooraankondiging]);
});

it('matches nothing when the filter carries a value that is not a role', function () {
    // Filter state comes back from the request, so an unknown value is
    // reachable. It has to behave like the empty `in` it replaces rather than
    // collapse into a condition group that matches every row.
    $vergunning = ($this->zaakOp)('Evenementenvergunning Testgemeente');

    ($this->asPlatformAdmin)();

    livewire(ListZaken::class)
        ->assertCanSeeTableRecords([$vergunning])
        ->filterTable('role', ['geen-rol'])
        ->assertCanNotSeeTableRecords([$vergunning])
        ->assertCountTableRecords(0);
});

it('only narrows a query that was already scoped', function () {
    // The per-municipality scoping does not live in this filter: it reaches the
    // list as a constraint on the outer query, put there by the panel's
    // tenancy. All the filter does is add a whereHas on top of whatever it is
    // handed, so it can never bring a row back that the scoping left out. This
    // asserts that property on the query itself, which is where it holds.
    $other = Municipality::factory()->create(['name' => 'Andere gemeente']);

    $own = ($this->zaakOp)('Evenementenvergunning Testgemeente');
    ($this->zaakOp)('Evenementenvergunning Andere gemeente', null, $other);

    $scoped = fn (): Builder => Zaak::query()->whereHas(
        'zaaktype',
        fn (Builder $zaaktypen): Builder => $zaaktypen->where('municipality_id', $this->municipality->id)
    );

    $filtered = $scoped()->whereHas('zaaktype', function (Builder $zaaktypen): Builder {
        /** @var Builder<Zaaktype> $zaaktypen */
        return $zaaktypen->withEffectiveRoleIn([ZaaktypeRole::Vergunning->value]);
    });

    expect($scoped()->pluck('id')->all())->toBe([$own->id])
        ->and($filtered->pluck('id')->all())->toBe([$own->id]);
});
