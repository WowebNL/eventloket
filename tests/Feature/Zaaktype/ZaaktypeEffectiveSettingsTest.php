<?php

use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

const OWN_INSTANCE_BASE = 'https://gemeente.example.com';

test('effectiveTriggersRouteCheck prefers the blueprint override', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => 'EVG-1',
        'municipality_id' => $municipality->id,
        'triggers_route_check' => false,
    ]);

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'zaaktype_identificatie' => 'EVG-1',
        'triggers_route_check' => true,
    ]);

    expect($zaaktype->fresh()->effectiveTriggersRouteCheck())->toBeTrue();
});

test('effectiveTriggersRouteCheck falls back to the row value without an override', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => 'EVG-2',
        'municipality_id' => $municipality->id,
        'triggers_route_check' => true,
    ]);

    expect($zaaktype->effectiveTriggersRouteCheck())->toBeTrue();
});

test('effectiveHiddenResultaatTypeRules uses the row base and the blueprint override', function () {
    $municipality = Municipality::factory()->create();

    $adminRow = Zaaktype::factory()->create([
        'identificatie' => 'A',
        'municipality_id' => $municipality->id,
        'hidden_resultaat_types' => ['https://x/result/1'],
    ]);

    $ownRow = Zaaktype::factory()->create([
        'identificatie' => 'B',
        'municipality_id' => $municipality->id,
        'connection' => "gemeente_{$municipality->id}",
        'hidden_resultaat_types' => ['https://x/result/old'],
    ]);

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'B',
        'hidden_resultaat_types' => ['https://x/result/new'],
    ]);

    $rules = collect(Zaaktype::effectiveHiddenResultaatTypeRules());

    // Without a koppeling every zaak of the zaaktype gets the row value.
    expect($rules->where('zaaktype_id', $adminRow->id)->values()->all())->toBe([
        ['zaaktype_id' => $adminRow->id, 'role' => null, 'except_roles' => [], 'hidden' => ['https://x/result/1']],
    ])
        // With one, the koppeling overrides it for its own role and, as the
        // only koppeling of the zaaktype, for zaken without a stored role.
        ->and($rules->where('zaaktype_id', $ownRow->id)->values()->all())->toBe([
            ['zaaktype_id' => $ownRow->id, 'role' => 'vergunning', 'except_roles' => [], 'hidden' => ['https://x/result/new']],
            ['zaaktype_id' => $ownRow->id, 'role' => null, 'except_roles' => ['vergunning'], 'hidden' => ['https://x/result/new']],
        ]);
});

test('saving a koppeling for an own-instance gemeente creates the local zaaktype row immediately', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $municipality->id]);

    $ownUrl = OWN_INSTANCE_BASE.'/catalogi/api/v1/zaaktypen/own-1';

    Http::fake([
        OWN_INSTANCE_BASE.'/catalogi/api/v1/zaaktypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $ownUrl, 'identificatie' => 'OWN-1', 'omschrijving' => 'Eigen zaaktype', 'beginGeldigheid' => '2026-01-01', 'versiedatum' => '2026-01-01'],
        ]), 200),
    ]);

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'OWN-1',
    ]);

    $zaaktype = Zaaktype::where('identificatie', 'OWN-1')
        ->where('connection', "gemeente_{$municipality->id}")
        ->first();

    expect($zaaktype)->not->toBeNull()
        ->and($zaaktype->municipality_id)->toBe($municipality->id)
        ->and($zaaktype->zgw_zaaktype_url)->toBe($ownUrl)
        ->and($zaaktype->role)->toBe(ZaaktypeRole::Vergunning);
});
