<?php

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\EventForm\Submit\Steps\CreateZaakInZGW;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

test('CreateZaakInZGW sends the version valid on the creation date as zaaktype', function () {
    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => 'EVG-HEERLEN',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/latest',
    ]);

    $versionUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/valid-today';
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/new-1';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $versionUrl, 'identificatie' => 'EVG-HEERLEN'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken' => Http::response([
            'url' => $zaakUrl,
            'uuid' => 'new-1',
            'identificatie' => 'ZAAK-1',
            'zaaktype' => $versionUrl,
            'omschrijving' => 'Test',
            'startdatum' => now()->toDateString(),
            'registratiedatum' => now()->toDateString(),
            'einddatum' => null,
            'einddatumGepland' => null,
            'uiterlijkeEinddatumAfdoening' => null,
            'bronorganisatie' => '820151130',
            'zaakgeometrie' => null,
        ], 201),
    ]);

    $oz = app(CreateZaakInZGW::class)->execute(
        new FormState(values: ['watIsDeNaamVanHetEvenementVergunning' => 'Test']),
        $zaaktype,
    );

    expect($oz->zaaktype)->toBe($versionUrl);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/zaken/api/v1/zaken')
        && $request->method() === 'POST'
        && $request['zaaktype'] === $versionUrl);

    // The version resolution used the standard ZTC filters.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/catalogi/api/v1/zaaktypen')
        && str_contains($request->url(), 'identificatie=EVG-HEERLEN')
        && str_contains($request->url(), 'datumGeldigheid=')
        && str_contains($request->url(), 'status=definitief'));
});

test('CreateZaakInZGW resolves the zaaktype from the blueprint mapping identificatie, not the local row', function () {
    // A municipality whose local zaaktype was synced from the central OpenZaak
    // (its own identificatie/url point at that instance), while the blueprint
    // mapping names the identificatie in the municipality's own catalogus.
    $municipality = Municipality::factory()->create();
    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vergunning->value,
        'zaaktype_identificatie' => 'GEMEENTE-ID',
    ]);

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'identificatie' => 'CENTRAL-ID',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/central',
    ]);

    $versionUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/gemeente-valid';
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/new-1';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $versionUrl, 'identificatie' => 'GEMEENTE-ID'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken' => Http::response([
            'url' => $zaakUrl,
            'uuid' => 'new-1',
            'identificatie' => 'ZAAK-1',
            'zaaktype' => $versionUrl,
            'omschrijving' => 'Test',
            'startdatum' => now()->toDateString(),
            'registratiedatum' => now()->toDateString(),
            'bronorganisatie' => '820151130',
            'zaakgeometrie' => null,
        ], 201),
    ]);

    $oz = app(CreateZaakInZGW::class)->execute(
        new FormState(values: ['watIsDeNaamVanHetEvenementVergunning' => 'Test']),
        $zaaktype,
    );

    expect($oz->zaaktype)->toBe($versionUrl);

    // The version lookup used the mapping identificatie, not the local row's.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/catalogi/api/v1/zaaktypen')
        && str_contains($request->url(), 'identificatie=GEMEENTE-ID'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'identificatie=CENTRAL-ID'));
});

test('zgwZaaktypeVersionUrl prefers the snapshot and falls back to the zaaktype url', function () {
    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/latest',
    ]);

    $withSnapshot = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => null,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/snap',
    ]);

    $withoutSnapshot = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => null,
        'zgw_zaaktype_url' => null,
    ]);

    expect($withSnapshot->zgwZaaktypeVersionUrl())->toBe(ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/snap')
        ->and($withoutSnapshot->zgwZaaktypeVersionUrl())->toBe(ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/latest');
});

test('document_types reads against the zaak version snapshot, not the latest zaaktype url', function () {
    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/latest',
    ]);

    $snapshotUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/snap';

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => null,
        'zgw_zaaktype_url' => $snapshotUrl,
    ]);

    Http::fake([
        // Document types are resolved via the standard zaaktype-informatieobjecttypen
        // relation, then each linked informatieobjecttype is fetched by url.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen/1', 'zaaktype' => $snapshotUrl, 'informatieobjecttype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/1', 'omschrijving' => 'Bijlage', 'vertrouwelijkheidaanduiding' => 'zaakvertrouwelijk',
        ], 200),
    ]);

    expect($zaak->document_types)->toHaveCount(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/catalogi/api/v1/zaaktype-informatieobjecttypen')
        && str_contains($request->url(), 'zaaktype='.urlencode($snapshotUrl)));
});

test('backfill sets identificatie, collapses duplicate versions and snapshots the version on repointed zaken', function () {
    $urlA = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/aaa';
    $urlB = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/bbb';

    $rowA = Zaaktype::factory()->create(['identificatie' => null, 'zgw_zaaktype_url' => $urlA]);
    $rowB = Zaaktype::factory()->create(['identificatie' => null, 'zgw_zaaktype_url' => $urlB]);

    // A zaak created against the (older) B version.
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $rowB->id,
        'zgw_zaak_url' => null,
        'zgw_zaaktype_url' => null,
    ]);

    Http::fake([
        $urlA => Http::response(['url' => $urlA, 'identificatie' => 'EVG-HEERLEN'], 200),
        $urlB => Http::response(['url' => $urlB, 'identificatie' => 'EVG-HEERLEN'], 200),
    ]);

    $this->artisan('app:backfill-zaaktype-identificatie')->assertSuccessful();

    // Both rows shared an identificatie, so they collapse to one (survivor = url-sorted first = A).
    $remaining = Zaaktype::where('identificatie', 'EVG-HEERLEN')->get();

    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->id)->toBe($rowA->id);

    $zaak->refresh();

    // The zaak is repointed to the survivor and keeps a snapshot of the version it used.
    expect($zaak->zaaktype_id)->toBe($rowA->id)
        ->and($zaak->zgw_zaaktype_url)->toBe($urlB);
});
