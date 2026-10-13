<?php

declare(strict_types=1);

/**
 * The two migrations that let several roles share one zaaktype: the first
 * stores the role on the zaak and backfills it, the second drops the unique
 * index that kept a zaaktype to a single koppeling.
 */

use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function zaakRoleMigration(): Migration
{
    return require database_path('migrations/2026_10_13_090000_add_role_to_zaken_table.php');
}

function sharedZaaktypeMappingMigration(): Migration
{
    return require database_path('migrations/2026_10_13_090100_allow_shared_zaaktype_across_municipality_zaaktype_mappings.php');
}

function sharedZaaktypeMappingIndexes(): array
{
    return collect(Schema::getIndexes('municipality_zaaktype_mappings'))
        ->filter(fn (array $index): bool => collect($index['columns'])->sort()->values()->all() === ['municipality_id', 'zaaktype_identificatie'])
        ->values()
        ->all();
}

function zaakForRoleBackfill(Zaaktype $zaaktype, array $attributes = []): Zaak
{
    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => Organisation::factory()->create()->id,
        ...$attributes,
    ]);
}

function storedZaakRole(Zaak $zaak): ?string
{
    return DB::table('zaken')->where('id', $zaak->id)->value('role');
}

afterEach(function () {
    // Leave the schema as the full migration run left it, also on an engine
    // that commits DDL outside the test transaction.
    if (! Schema::hasColumn('zaken', 'role')) {
        zaakRoleMigration()->up();
    }

    if (collect(sharedZaaktypeMappingIndexes())->contains('unique', true)) {
        DB::table('municipality_zaaktype_mappings')->delete();
        sharedZaaktypeMappingMigration()->up();
    }
});

it('backfills the role of existing zaken with the same ladder the zaaktype uses', function () {
    $municipality = Municipality::factory()->create();

    // Rung 1: a koppeling, whatever the zaaktype is called.
    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vooraankondiging,
        'zaaktype_identificatie' => 'SYN-KOPPELING',
    ]);
    $gekoppeld = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'identificatie' => 'SYN-KOPPELING',
        'name' => 'Melding met een andere naam',
        'role' => ZaaktypeRole::Melding,
    ]);

    // Rung 2: the role column.
    $metRol = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Vooraankondiging met een andere rol',
        'role' => ZaaktypeRole::Melding,
    ]);

    // Rung 3: the naming convention, case-insensitive.
    $opNaam = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'EVENEMENTENVERGUNNING gemeente Voorbeeld',
        'role' => null,
    ]);

    // Nothing to go on.
    $onbekend = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Iets anders',
        'role' => null,
    ]);

    $zaakGekoppeld = zaakForRoleBackfill($gekoppeld);
    $zaakMetRol = zaakForRoleBackfill($metRol);
    $zaakOpNaam = zaakForRoleBackfill($opNaam);
    $zaakOnbekend = zaakForRoleBackfill($onbekend);
    // A deelzaak is a route passage, whichever zaaktype it carries.
    $deelzaak = zaakForRoleBackfill($opNaam, ['hoofdzaak_id' => $zaakOpNaam->id]);

    zaakRoleMigration()->down();
    zaakRoleMigration()->up();

    expect(storedZaakRole($zaakGekoppeld))->toBe('vooraankondiging')
        ->and(storedZaakRole($zaakMetRol))->toBe('melding')
        ->and(storedZaakRole($zaakOpNaam))->toBe('vergunning')
        ->and(storedZaakRole($zaakOnbekend))->toBeNull()
        ->and(storedZaakRole($deelzaak))->toBe('doorkomst');
});

it('also backfills soft-deleted zaken', function () {
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => Municipality::factory()->create()->id,
        'role' => ZaaktypeRole::Melding,
    ]);
    $zaak = zaakForRoleBackfill($zaaktype);
    $zaak->delete();

    zaakRoleMigration()->down();
    zaakRoleMigration()->up();

    expect(storedZaakRole($zaak))->toBe('melding');
});

it('lets a municipality map several roles to the same zaaktype', function () {
    $municipality = Municipality::factory()->create();

    foreach ([ZaaktypeRole::Vergunning, ZaaktypeRole::Melding, ZaaktypeRole::Vooraankondiging] as $role) {
        MunicipalityZaaktypeMapping::create([
            'municipality_id' => $municipality->id,
            'role' => $role,
            'zaaktype_identificatie' => 'SYN-GEDEELD',
        ]);
    }

    $indexes = sharedZaaktypeMappingIndexes();

    expect(MunicipalityZaaktypeMapping::where('zaaktype_identificatie', 'SYN-GEDEELD')->count())->toBe(3)
        ->and($indexes)->toHaveCount(1)
        ->and($indexes[0]['unique'])->toBeFalse();
});

it('restores the unique index on rollback when no zaaktype is shared', function () {
    sharedZaaktypeMappingMigration()->down();

    $indexes = sharedZaaktypeMappingIndexes();

    expect($indexes)->toHaveCount(1)
        ->and($indexes[0]['unique'])->toBeTrue();
});

it('refuses to roll back while a zaaktype is shared and names it', function () {
    $municipality = Municipality::factory()->create();

    foreach ([ZaaktypeRole::Melding, ZaaktypeRole::Vooraankondiging] as $role) {
        MunicipalityZaaktypeMapping::create([
            'municipality_id' => $municipality->id,
            'role' => $role,
            'zaaktype_identificatie' => 'SYN-GEDEELD',
        ]);
    }

    try {
        sharedZaaktypeMappingMigration()->down();
        $this->fail('The rollback should have refused while a zaaktype is shared.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('SYN-GEDEELD')
            ->toContain('melding')
            ->toContain('vooraankondiging');
    }

    expect(collect(sharedZaaktypeMappingIndexes())->contains('unique', true))->toBeFalse();
});
