<?php

declare(strict_types=1);

use App\Services\Zgw\ZaaktypeCatalogusOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Cache::flush();
});

function fakeInformatieobjecttypeChain(array $typeResponses): void
{
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $zaaktypeUrl = $base.'/zaaktypen/1';

    $relations = [];
    $stubs = [];
    foreach ($typeResponses as $i => $response) {
        $typeUrl = $base.'/informatieobjecttypen/'.($i + 1);
        $relations[] = ['informatieobjecttype' => $typeUrl];
        $stubs[$typeUrl] = $response;
    }

    Http::fake(array_merge([
        // versionUrl(): resolve the definitief version for the identificatie.
        $base.'/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([['url' => $zaaktypeUrl]]), 200),
        // the zaaktype-informatieobjecttypen relation list.
        $base.'/zaaktype-informatieobjecttypen?*' => Http::response(ZgwHttpFake::envelope($relations), 200),
    ], $stubs));
}

test('document types list the readable informatieobjecttypen', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';

    fakeInformatieobjecttypeChain([
        Http::response(['url' => $base.'/informatieobjecttypen/1', 'omschrijving' => 'Vergunning'], 200),
        Http::response(['url' => $base.'/informatieobjecttypen/2', 'omschrijving' => 'Bijlage'], 200),
    ]);

    expect(ZaaktypeCatalogusOptions::informatieobjecttypen('main', 'ZT-1'))
        ->toBe(['Vergunning' => 'Vergunning', 'Bijlage' => 'Bijlage']);
});

test('an unreadable informatieobjecttype is skipped, not fatal', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';

    fakeInformatieobjecttypeChain([
        Http::response(['url' => $base.'/informatieobjecttypen/1', 'omschrijving' => 'Vergunning'], 200),
        Http::response(['detail' => 'nope'], 500),
    ]);

    expect(ZaaktypeCatalogusOptions::informatieobjecttypen('main', 'ZT-1'))
        ->toBe(['Vergunning' => 'Vergunning']);
});

test('document types use the inline omschrijving when the relation is not a url (RX Mission)', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';

    Http::fake([
        $base.'/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/1']]), 200),
        $base.'/zaaktype-informatieobjecttypen?*' => Http::response(ZgwHttpFake::envelope([
            ['informatieobjecttype' => 'Aanvraag'],
            ['informatieobjecttype' => 'Bijlage'],
        ]), 200),
    ]);

    expect(ZaaktypeCatalogusOptions::informatieobjecttypen('main', 'ZT-1'))
        ->toBe(['Aanvraag' => 'Aanvraag', 'Bijlage' => 'Bijlage']);
});

test('the zaaktype version is resolved once and reused across resource lists', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';

    Http::fake([
        $base.'/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/1']]), 200),
        $base.'/statustypen?*' => Http::response(ZgwHttpFake::envelope([['omschrijving' => 'Ontvangen', 'volgnummer' => 1]]), 200),
        $base.'/roltypen?*' => Http::response(ZgwHttpFake::envelope([['omschrijving' => 'Aanvrager']]), 200),
    ]);

    ZaaktypeCatalogusOptions::statustypen('main', 'ZT-1');
    ZaaktypeCatalogusOptions::roltypen('main', 'ZT-1');

    // The version lookup (a /zaaktypen read) is cached per (connection,
    // identificatie), so it runs once even though two resource lists need it.
    $versionLookups = Http::recorded(fn ($request) => str_contains($request->url(), '/catalogi/api/v1/zaaktypen?'))->count();
    expect($versionLookups)->toBe(1);
});

test('eigenschappen resolve the version valid today and list the naam', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';

    Http::fake([
        $base.'/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/9']]), 200),
        $base.'/eigenschappen?*' => Http::response(ZgwHttpFake::envelope([
            ['naam' => 'risico_classificatie'],
            ['naam' => 'aantal_bezoekers'],
        ]), 200),
    ]);

    expect(ZaaktypeCatalogusOptions::eigenschappen('main', 'ZT-1'))
        ->toBe(['risico_classificatie' => 'risico_classificatie', 'aantal_bezoekers' => 'aantal_bezoekers']);

    // The version is resolved with a datumGeldigheid filter so only the
    // currently-valid definitief version is used.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaaktypen')
        && str_contains($request->url(), 'datumGeldigheid='));
});
