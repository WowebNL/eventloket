<?php

use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resolves the doorkomst zaaktype via the role=Doorkomst blueprint', function () {
    $municipality = Municipality::factory()->create();

    $expected = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'identificatie' => 'DKX-1',
        'is_active' => true,
    ]);

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'zaaktype_identificatie' => 'DKX-1',
    ]);

    expect($municipality->resolveDoorkomstZaaktype()?->id)->toBe($expected->id);
});

test('resolves the doorkomst zaaktype via the explicit role column when no blueprint exists', function () {
    $municipality = Municipality::factory()->create();

    $expected = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'is_active' => true,
    ]);

    expect($municipality->resolveDoorkomstZaaktype()?->id)->toBe($expected->id);
});

test('falls back to the legacy doorkomst_zaaktype_id FK', function () {
    $municipality = Municipality::factory()->create();

    $legacy = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'is_active' => true,
    ]);
    // doorkomst_zaaktype_id is guarded from mass assignment, set as SyncZaaktypen does.
    $municipality->doorkomst_zaaktype_id = $legacy->id;
    $municipality->save();

    expect($municipality->resolveDoorkomstZaaktype()?->id)->toBe($legacy->id);
});

test('returns null when nothing is configured or the candidate is inactive', function () {
    $municipality = Municipality::factory()->create();

    $inactive = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'is_active' => false,
    ]);
    $municipality->doorkomst_zaaktype_id = $inactive->id;
    $municipality->save();

    expect($municipality->resolveDoorkomstZaaktype())->toBeNull();
});

/**
 * A doorkomst zaaktype only exists in the catalogus of the instance that hosts it.
 * When a municipality's own connection cannot be used its traffic routes to main,
 * so the zaaktype has to move with it; otherwise the deelzaak is created on main
 * against a zaaktype main does not host.
 */
test('moves the doorkomst zaaktype to the main catalogus when the own connection cannot be used', function () {
    $municipality = Municipality::factory()->create(['name' => 'Doorkomstgemeente']);

    MunicipalityZgwConnection::factory()->create([
        'municipality_id' => $municipality->id,
        'activated_at' => null,
    ]);

    Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => "gemeente_{$municipality->id}",
        'is_active' => true,
    ]);

    $main = Zaaktype::factory()->create([
        'name' => 'Doorkomst gemeente Doorkomstgemeente',
        'municipality_id' => null,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'is_active' => true,
    ]);

    expect($municipality->resolveDoorkomstZaaktype()?->id)->toBe($main->id);
});

test('keeps the own doorkomst zaaktype when the own connection is usable', function () {
    $municipality = Municipality::factory()->create(['name' => 'Doorkomstgemeente']);

    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
    ]);

    // The main row a previous fallback linked to this municipality stays linked, so
    // both rows match the role lookup. It is created first on purpose: without an
    // explicit ordering the lookup would hand back the fallback row and the
    // municipality would keep using main after its own instance is usable again.
    Zaaktype::factory()->create([
        'name' => 'Doorkomst gemeente Doorkomstgemeente',
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'is_active' => true,
    ]);

    $own = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => "gemeente_{$municipality->id}",
        'is_active' => true,
    ]);

    expect($municipality->resolveDoorkomstZaaktype()?->id)->toBe($own->id);
});
