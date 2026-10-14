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

/**
 * Fake a catalogus whose current definitief version offers the given
 * resultaattypen (url suffix => omschrijving), plus direct reads of the
 * withdrawn urls a previous version published.
 *
 * @param  array<string, string>  $current  url suffix => omschrijving
 * @param  array<string, string|null>  $withdrawn  full url => omschrijving, or null for a url the backend reports as gone
 */
function fakeResultaattypeVersions(array $current, array $withdrawn = []): void
{
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';

    $results = [];
    foreach ($current as $suffix => $omschrijving) {
        $results[] = ['url' => $base.'/resultaattypen/'.$suffix, 'omschrijving' => $omschrijving];
    }

    $stubs = [];
    foreach ($withdrawn as $url => $omschrijving) {
        $stubs[$url] = $omschrijving === null
            ? Http::response(['detail' => 'not found'], 404)
            : Http::response(['url' => $url, 'omschrijving' => $omschrijving], 200);
    }

    Http::fake(array_merge($stubs, [
        $base.'/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/2']]), 200),
        $base.'/resultaattypen?*' => Http::response(ZgwHttpFake::envelope($results), 200),
    ]));
}

test('stored resultaattype urls that survive a republish are re-pointed at the current version', function () {
    // Republishing a zaaktype gives every resultaattype a new url while the
    // omschrijving stays the same, so a selection stored against the previous
    // version names urls the current version does not have. Those urls are
    // followed to their omschrijving and matched onto the current ones.
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $oldVerleend = $base.'/resultaattypen/old-1';
    $oldIngetrokken = $base.'/resultaattypen/old-2';

    fakeResultaattypeVersions(
        current: ['new-1' => 'Verleend', 'new-2' => 'Ingetrokken', 'new-3' => 'Geweigerd'],
        withdrawn: [$oldVerleend => 'Verleend', $oldIngetrokken => 'Ingetrokken'],
    );

    expect(ZaaktypeCatalogusOptions::reconcileResultaattypeUrls('main', 'ZT-1', [$oldVerleend, $oldIngetrokken]))
        ->toBe([$base.'/resultaattypen/new-1', $base.'/resultaattypen/new-2']);
});

test('a stored resultaattype url the current version still has is left untouched', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';

    fakeResultaattypeVersions(current: ['new-1' => 'Verleend', 'new-2' => 'Ingetrokken']);

    expect(ZaaktypeCatalogusOptions::reconcileResultaattypeUrls('main', 'ZT-1', [$base.'/resultaattypen/new-2']))
        ->toBe([$base.'/resultaattypen/new-2']);

    // A url the current version already has needs no lookup of its own.
    Http::assertNotSent(fn ($request): bool => $request->url() === $base.'/resultaattypen/new-2');
});

test('a stored resultaattype that no longer exists under any url is dropped', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $removed = $base.'/resultaattypen/old-9';
    $gone = $base.'/resultaattypen/old-8';

    fakeResultaattypeVersions(
        current: ['new-1' => 'Verleend'],
        // One resolves to an omschrijving the current version no longer offers,
        // the other the backend reports as gone. Both are answers about the
        // stored url, so both may be acted on.
        withdrawn: [$removed => 'Buiten behandeling', $gone => null],
    );

    expect(ZaaktypeCatalogusOptions::reconcileResultaattypeUrls('main', 'ZT-1', [$removed, $gone]))
        ->toBe([]);
});

test('a stored resultaattype whose own read fails is kept, not dropped', function () {
    // The list read succeeds (it may even come from the cache) while the read
    // of this one url fails with a 500. That is not evidence the resultaattype
    // is gone, and dropping it here would be permanent as soon as the koppeling
    // is saved, so the stored selection is left exactly as it is.
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $stale = $base.'/resultaattypen/old-1';

    Http::fake([
        $stale => Http::response(['detail' => 'nope'], 500),
        $base.'/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/2']]), 200),
        $base.'/resultaattypen?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/resultaattypen/new-1', 'omschrijving' => 'Verleend'],
        ]), 200),
    ]);

    expect(ZaaktypeCatalogusOptions::reconcileResultaattypeUrls('main', 'ZT-1', [$stale]))
        ->toBe([$stale]);
});

test('a rejected read of a stored resultaattype keeps it too', function () {
    // A 401 or 403 says something about our credentials, not about whether the
    // resultaattype still exists, so it is treated like any other failed read.
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $stale = $base.'/resultaattypen/old-1';

    Http::fake([
        $stale => Http::response(['detail' => 'no'], 403),
        $base.'/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/2']]), 200),
        $base.'/resultaattypen?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/resultaattypen/new-1', 'omschrijving' => 'Verleend'],
        ]), 200),
    ]);

    expect(ZaaktypeCatalogusOptions::reconcileResultaattypeUrls('main', 'ZT-1', [$stale]))
        ->toBe([$stale]);
});

test('the option lists of one zaaktype are forgotten together', function () {
    // Every per-zaaktype list hangs off the same definitief version, so a
    // republish makes them all stale at once and they are dropped as a set.
    // The catalogus answers with the first version until it is asked again.
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';

    Http::fake([
        $base.'/zaaktypen?*' => Http::sequence()
            ->push(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/1']]), 200)
            ->push(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/2']]), 200),
        $base.'/statustypen?*' => Http::sequence()
            ->push(ZgwHttpFake::envelope([['omschrijving' => 'Ontvangen', 'volgnummer' => 1]]), 200)
            ->push(ZgwHttpFake::envelope([['omschrijving' => 'Afgehandeld', 'volgnummer' => 1]]), 200),
    ]);

    // The second call is served from the cache, so it leaves the next response
    // in the sequence untouched.
    expect(ZaaktypeCatalogusOptions::statustypen('main', 'ZT-1'))->toBe(['Ontvangen' => '1. Ontvangen'])
        ->and(ZaaktypeCatalogusOptions::statustypen('main', 'ZT-1'))->toBe(['Ontvangen' => '1. Ontvangen']);

    ZaaktypeCatalogusOptions::forgetZaaktype('main', 'ZT-1');

    expect(ZaaktypeCatalogusOptions::statustypen('main', 'ZT-1'))
        ->toBe(['Afgehandeld' => '1. Afgehandeld']);
});

test('an unreadable resultaattypen list leaves the stored selection alone', function () {
    // Every catalogi read degrades to an empty list on failure, so an empty
    // list cannot be told apart from an unreachable backend: discarding the
    // selection there would throw away a configuration over a hiccup.
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $stored = [$base.'/resultaattypen/old-1'];

    Http::fake([
        $base.'/zaaktypen?*' => Http::response(ZgwHttpFake::envelope([['url' => $base.'/zaaktypen/2']]), 200),
        $base.'/resultaattypen?*' => Http::response(['detail' => 'nope'], 500),
    ]);

    expect(ZaaktypeCatalogusOptions::reconcileResultaattypeUrls('main', 'ZT-1', $stored))->toBe($stored);
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
