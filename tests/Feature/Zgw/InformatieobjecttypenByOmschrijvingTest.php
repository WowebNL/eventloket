<?php

declare(strict_types=1);

/**
 * ZgwResource::informatieobjecttypenByOmschrijving() lists a catalogus'
 * informatieobjecttypen and keys them by omschrijving, so a relation that
 * carries an omschrijving string (rather than a URL) can be resolved to the
 * full resource. When several versions share an omschrijving, the one valid
 * today wins; otherwise the first seen is kept.
 */

use App\Services\Zgw\ZgwResource;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('the version valid today wins over an expired one and the uuid is derived', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $catalogusUrl = $base.'/catalogussen/1';

    Http::fake([
        $base.'/informatieobjecttypen?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/informatieobjecttypen/expired', 'omschrijving' => 'Advies', 'catalogus' => $catalogusUrl, 'beginGeldigheid' => '2000-01-01', 'eindeGeldigheid' => '2000-12-31'],
            ['url' => $base.'/informatieobjecttypen/current', 'omschrijving' => 'Advies', 'catalogus' => $catalogusUrl, 'beginGeldigheid' => '2000-01-01', 'eindeGeldigheid' => null],
        ]), 200),
    ]);

    $map = ZgwResource::informatieobjecttypenByOmschrijving('main', $catalogusUrl);

    expect($map)->toHaveKey('Advies');
    expect($map['Advies']['url'])->toBe($base.'/informatieobjecttypen/current');
    // uuid is derived from the trailing url segment by ensureUuid().
    expect($map['Advies']['uuid'])->toBe('current');
});

test('with no valid-today version the first one seen is kept', function () {
    $base = ZgwHttpFake::$baseUrl.'/catalogi/api/v1';
    $catalogusUrl = $base.'/catalogussen/1';

    Http::fake([
        $base.'/informatieobjecttypen?*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $base.'/informatieobjecttypen/first', 'omschrijving' => 'Advies', 'catalogus' => $catalogusUrl, 'beginGeldigheid' => '2000-01-01', 'eindeGeldigheid' => '2000-12-31'],
            ['url' => $base.'/informatieobjecttypen/second', 'omschrijving' => 'Advies', 'catalogus' => $catalogusUrl, 'beginGeldigheid' => '2001-01-01', 'eindeGeldigheid' => '2001-12-31'],
        ]), 200),
    ]);

    $map = ZgwResource::informatieobjecttypenByOmschrijving('main', $catalogusUrl);

    expect($map['Advies']['url'])->toBe($base.'/informatieobjecttypen/first');
});
