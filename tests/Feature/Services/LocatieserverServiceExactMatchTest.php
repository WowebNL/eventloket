<?php

declare(strict_types=1);

/**
 * The BAG lookup by postcode + house number must return the address only when
 * it exactly matches the requested postcode and house number. PDOK's free-text
 * search is fuzzy and returns a nearby address for a non-existent house number;
 * without an exact-match guard that wrong address gets auto-filled in the form.
 */

use App\Services\LocatieserverService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.locatieserver.base_url', 'https://locatieserver.test');
});

function fakePdokDoc(array $overrides = []): array
{
    return array_merge([
        'id' => 'adr-1',
        'type' => 'adres',
        'centroide_ll' => 'POINT(5.88 50.91)',
        'weergavenaam' => 'Deweverplein 1, 6361BZ Nuth',
        'straatnaam' => 'Deweverplein',
        'postcode' => '6361BZ',
        'huisnummer' => 1,
        'woonplaatsnaam' => 'Nuth',
        'gemeentecode' => '1954',
    ], $overrides);
}

test('returns the address when postcode and house number match exactly', function () {
    Http::fake([
        'https://locatieserver.test/search/v3_1/free*' => Http::response([
            'response' => ['docs' => [fakePdokDoc()]],
        ]),
    ]);

    $bag = (new LocatieserverService)->getBagObjectByPostcodeHuisnummer('6361BZ', '1');

    expect($bag)->not->toBeNull()
        ->and($bag->straatnaam)->toBe('Deweverplein')
        ->and($bag->woonplaatsnaam)->toBe('Nuth');
});

test('matches despite spacing and casing differences in the postcode', function () {
    Http::fake([
        'https://locatieserver.test/search/v3_1/free*' => Http::response([
            'response' => ['docs' => [fakePdokDoc()]],
        ]),
    ]);

    // User types "6361 bz", PDOK returns "6361BZ".
    $bag = (new LocatieserverService)->getBagObjectByPostcodeHuisnummer('6361 bz', '1');

    expect($bag)->not->toBeNull()
        ->and($bag->straatnaam)->toBe('Deweverplein');
});

test('returns null when the returned house number differs from the requested one', function () {
    // PDOK returns house number 1 for the fuzzy query "6361 BZ 999".
    Http::fake([
        'https://locatieserver.test/search/v3_1/free*' => Http::response([
            'response' => ['docs' => [fakePdokDoc()]],
        ]),
    ]);

    $bag = (new LocatieserverService)->getBagObjectByPostcodeHuisnummer('6361BZ', '999');

    expect($bag)->toBeNull();
});

test('returns null when the returned postcode differs from the requested one', function () {
    Http::fake([
        'https://locatieserver.test/search/v3_1/free*' => Http::response([
            'response' => ['docs' => [fakePdokDoc(['postcode' => '6361BZ', 'huisnummer' => 1])]],
        ]),
    ]);

    // Different postcode, same house number: must not match.
    $bag = (new LocatieserverService)->getBagObjectByPostcodeHuisnummer('6400AA', '1');

    expect($bag)->toBeNull();
});

test('picks the exact match out of several fuzzy candidates', function () {
    Http::fake([
        'https://locatieserver.test/search/v3_1/free*' => Http::response([
            'response' => ['docs' => [
                fakePdokDoc(['id' => 'other', 'huisnummer' => 3, 'straatnaam' => 'Andere straat']),
                fakePdokDoc(['id' => 'wanted', 'huisnummer' => 12, 'straatnaam' => 'Deweverplein']),
            ]],
        ]),
    ]);

    $bag = (new LocatieserverService)->getBagObjectByPostcodeHuisnummer('6361BZ', '12');

    expect($bag)->not->toBeNull()
        ->and($bag->huisnummer)->toBe('12')
        ->and($bag->straatnaam)->toBe('Deweverplein');
});
