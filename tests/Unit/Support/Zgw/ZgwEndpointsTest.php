<?php

use App\Support\Zgw\ZgwEndpoints;

beforeEach(function () {
    config([
        'openzaak.url' => 'https://zgw.example.com/',
        'openzaak.zaken_base_url' => null,
        'openzaak.catalogi_base_url' => null,
        'openzaak.documenten_base_url' => null,
        'openzaak.besluiten_base_url' => null,
        'openzaak.catalogi_url' => null,
        'openzaak.openklant.url' => null,
        'openzaak.objectsapi.url' => null,
        'zgw.connections' => [],
    ]);
});

it('recognises a call under the configured base url', function () {
    expect(ZgwEndpoints::isZgwUrl('https://zgw.example.com/zaken/api/v1/statussen'))->toBeTrue()
        ->and(ZgwEndpoints::isZgwUrl('https://zgw.example.com/catalogi/api/v1/statustypen?zaaktype=x'))->toBeTrue()
        ->and(ZgwEndpoints::isZgwUrl('https://zgw.example.com'))->toBeTrue();
});

it('does not recognise another host', function () {
    expect(ZgwEndpoints::isZgwUrl('https://api.pdok.nl/bzk/locatieserver/search/v3_1/free'))->toBeFalse()
        ->and(ZgwEndpoints::isZgwUrl('https://hooks.slack.com/services/T000/B000/secret'))->toBeFalse();
});

it('does not let a look-alike host through the prefix match', function () {
    expect(ZgwEndpoints::isZgwUrl('https://zgw.example.com.attacker.test/zaken'))->toBeFalse();
});

it('recognises the per-api override urls', function () {
    config([
        'openzaak.url' => null,
        'openzaak.documenten_base_url' => 'https://documenten.example.com/api/v1/',
    ]);

    expect(ZgwEndpoints::isZgwUrl('https://documenten.example.com/api/v1/enkelvoudiginformatieobjecten'))->toBeTrue()
        ->and(ZgwEndpoints::isZgwUrl('https://documenten.example.com/other/api'))->toBeFalse();
});

it('recognises a multi connection url from the zgw client config shape', function () {
    config(['zgw.connections' => [
        'main' => ['urls' => ['zaken' => 'https://zaken.example.com/api/v1/']],
        'gemeente-x' => ['urls' => ['zaken' => 'https://gemeente-x.example.com/zaken/api/v1']],
    ]]);

    expect(ZgwEndpoints::isZgwUrl('https://zaken.example.com/api/v1/zaken'))->toBeTrue()
        ->and(ZgwEndpoints::isZgwUrl('https://gemeente-x.example.com/zaken/api/v1/rollen'))->toBeTrue();
});

it('ignores empty configuration values', function () {
    config(['openzaak.url' => '', 'openzaak.catalogi_url' => '   ']);

    expect(ZgwEndpoints::baseUrls())->toBe([])
        ->and(ZgwEndpoints::isZgwUrl('https://zgw.example.com/zaken'))->toBeFalse();
});

it('normalises every base url to a single trailing slash', function () {
    config([
        'openzaak.url' => 'https://zgw.example.com//',
        'openzaak.catalogi_url' => 'https://zgw.example.com/catalogi/api/v1/catalogussen/1234',
    ]);

    expect(ZgwEndpoints::baseUrls())->toBe([
        'https://zgw.example.com/',
        'https://zgw.example.com/catalogi/api/v1/catalogussen/1234/',
    ]);
});
