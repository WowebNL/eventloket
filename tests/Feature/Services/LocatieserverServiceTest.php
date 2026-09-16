<?php

declare(strict_types=1);

/**
 * The Locatieserver lookups must request and expose the BAG nummeraanduiding id,
 * because AddGeometryZGW needs it as the ZGW ObjectAdres identificatie.
 */

use App\Services\LocatieserverService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.locatieserver.base_url', 'https://locatieserver.test');
});

test('getBagObjectByPostcodeHuisnummer requests and exposes the nummeraanduiding id', function () {
    Http::fake([
        'https://locatieserver.test/search/v3_1/free*' => Http::response([
            'response' => ['docs' => [[
                'id' => 'adr-1',
                'type' => 'adres',
                'centroide_ll' => 'POINT(5.98 50.88)',
                'weergavenaam' => 'Geleenstraat 25, 6411HP Heerlen',
                'straatnaam' => 'Geleenstraat',
                'postcode' => '6411HP',
                'huisnummer' => 25,
                'woonplaatsnaam' => 'Heerlen',
                'gemeentecode' => '0917',
                'nummeraanduiding_id' => '0917201313093153',
            ]]],
        ]),
    ]);

    $bag = (new LocatieserverService)->getBagObjectByPostcodeHuisnummer('6411HP', '25');

    expect($bag)->not->toBeNull();
    expect($bag->nummeraanduiding_id)->toBe('0917201313093153');
    expect($bag->straatnaam)->toBe('Geleenstraat');

    // The BAG nummeraanduiding must be part of the requested field list.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'search/v3_1/free')
        && str_contains(urldecode($request->url()), 'nummeraanduiding_id'));
});
