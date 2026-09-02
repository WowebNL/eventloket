<?php

use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
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
