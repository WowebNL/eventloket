<?php

declare(strict_types=1);

/**
 * AddGeometryZGW writes the zaakgeometrie and the address zaakobjecten onto
 * the ZGW zaak, and it only ever gets one chance: a zaak that already carries
 * a geometry is skipped, so whatever the first run writes is what the zaak
 * keeps.
 *
 * That makes an unreachable Locatieserver a different problem here than on a
 * page. A page degrades to "no address" and shows the address again on the
 * next render; this job would report success while permanently leaving the
 * address out of both the geometry and the zaakobjecten. So it writes nothing
 * and fails instead, which is what puts the work back on the queue.
 */

use App\EventForm\Submit\EventLocationGeometryBuilder;
use App\Exceptions\LocatieserverUnavailableException;
use App\Jobs\Zaak\AddGeometryZGW;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\LocatieserverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

const LOCATIESERVER_TEST_URL = 'https://locatieserver.test';

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Config::set('services.locatieserver.base_url', LOCATIESERVER_TEST_URL);
});

/**
 * A zaak whose snapshot answers the location question with a single building
 * address, so the geometry the job builds depends entirely on a Locatieserver
 * lookup succeeding.
 */
function zaakMetGebouwadres(): Zaak
{
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'is_active' => true,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/geometry-test',
        'form_state_snapshot' => [
            'values' => [
                'adresVanDeGebouwEn' => [
                    ['postcode' => '6361BZ', 'houseNumber' => '1'],
                ],
            ],
        ],
    ]);
}

/**
 * The ZGW zaak as OpenZaak returns it: no geometry yet, so the job has work
 * to do.
 *
 * @return array<string, mixed>
 */
function zgwZaakZonderGeometrie(string $url): array
{
    return [
        'url' => $url,
        'identificatie' => 'ZAAK-123',
        'omschrijving' => 'Test zaak',
        'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        'startdatum' => '2026-05-01',
        'registratiedatum' => '2026-05-01',
        'einddatum' => null,
        'einddatumGepland' => null,
        'uiterlijkeEinddatumAfdoening' => null,
        'bronorganisatie' => '820151130',
        'zaakgeometrie' => null,
    ];
}

/**
 * A PDOK document for the address in the snapshot above.
 *
 * @return array<string, mixed>
 */
function pdokAdres(): array
{
    return [
        'id' => '0123456789',
        'type' => 'adres',
        'centroide_ll' => 'POINT(5.88 50.91)',
        'weergavenaam' => 'Teststraat 1, 6361BZ Testdorp',
        'straatnaam' => 'Teststraat',
        'postcode' => '6361BZ',
        'huisnummer' => '1',
        'woonplaatsnaam' => 'Testdorp',
        'gemeentecode' => '0123',
    ];
}

/**
 * Answer both ZGW and Locatieserver from a single stub. One stub matters here:
 * the client runs every registered stub callback against every request, so a
 * callback that throws for an unreachable Locatieserver would throw for the
 * ZGW calls as well.
 *
 * @param  callable(Request): mixed  $locatieserver  how Locatieserver answers
 */
function fakeZgwEnLocatieserver(string $zaakUrl, callable $locatieserver): void
{
    Http::fake(function (Request $request) use ($zaakUrl, $locatieserver) {
        if (str_contains($request->url(), LOCATIESERVER_TEST_URL)) {
            return $locatieserver($request);
        }

        return Http::response(zgwZaakZonderGeometrie($zaakUrl), 200);
    });
}

test('an unreachable Locatieserver fails the job instead of writing an incomplete geometry', function () {
    $zaak = zaakMetGebouwadres();

    fakeZgwEnLocatieserver($zaak->zgw_zaak_url, function () {
        throw new ConnectionException('cURL error 28: Operation timed out for '.LOCATIESERVER_TEST_URL.'/search/v3_1/free?q=6361BZ+1');
    });

    expect(fn () => app()->call([new AddGeometryZGW($zaak), 'handle']))
        ->toThrow(LocatieserverUnavailableException::class);

    // Nothing may be written: the job skips a zaak that already has a
    // geometry, so a partial write here would never be corrected.
    Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/zaakobjecten'));
});

test('an address the Locatieserver does not know still lets the job finish', function () {
    $zaak = zaakMetGebouwadres();

    fakeZgwEnLocatieserver(
        $zaak->zgw_zaak_url,
        fn () => Http::response(['response' => ['docs' => []]], 200),
    );

    app()->call([new AddGeometryZGW($zaak), 'handle']);

    // Retrying an address PDOK has no record of would never produce a
    // different answer, so this is not a failure.
    Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
});

test('a successful lookup writes the geometry and the address zaakobject', function () {
    $zaak = zaakMetGebouwadres();

    fakeZgwEnLocatieserver(
        $zaak->zgw_zaak_url,
        fn () => Http::response(['response' => ['docs' => [pdokAdres()]]], 200),
    );

    app()->call([new AddGeometryZGW($zaak), 'handle']);

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/zaken/api/v1/zaken/geometry-test')
        && isset($request['zaakgeometrie']));

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/zaakobjecten')
        && $request['objectType'] === 'adres');
});

test('the job looks addresses up on the budget meant for background work', function () {
    $zaak = zaakMetGebouwadres();

    Config::set('services.locatieserver.background_connect_timeout', 5);
    Config::set('services.locatieserver.background_timeout', 20);

    $used = new ArrayObject;

    Http::fake(function (Request $request, array $options) use ($zaak, $used) {
        if (str_contains($request->url(), LOCATIESERVER_TEST_URL)) {
            $used->exchangeArray($options);

            return Http::response(['response' => ['docs' => []]], 200);
        }

        return Http::response(zgwZaakZonderGeometrie($zaak->zgw_zaak_url), 200);
    });

    app()->call([new AddGeometryZGW($zaak), 'handle']);

    expect($used['connect_timeout'])->toEqual(5.0)
        ->and($used['timeout'])->toEqual(20.0);
});

test('switching a builder to the background budget leaves the original alone', function () {
    $builder = new EventLocationGeometryBuilder(new LocatieserverService);

    expect($builder->forBackgroundWork())->not->toBe($builder);
});
