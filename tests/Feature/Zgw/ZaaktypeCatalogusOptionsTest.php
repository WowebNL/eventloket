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
