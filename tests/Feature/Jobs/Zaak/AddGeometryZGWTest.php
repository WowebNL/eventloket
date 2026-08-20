<?php

declare(strict_types=1);

/**
 * AddGeometryZGW patches the zaakgeometrie and registers each collected BAG
 * address as an "adres" zaakobject. ZGW ObjectAdres requires `identificatie`
 * (the BAG nummeraanduiding id) and expects the street name in
 * gorOpenbareRuimteNaam; a missing identificatie is rejected with a 400.
 */

use App\EventForm\Submit\EventLocationGeometryBuilder;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Jobs\Zaak\AddGeometryZGW;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\LocatieserverService;
use App\ValueObjects\Pdok\BagObject;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

test('an adres zaakobject carries the BAG nummeraanduiding as identificatie and the street name', function () {
    $zaakUrl = ZgwHttpFake::fakeSingleZaak(); // zaakgeometrie is null, so the job proceeds

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakobjecten' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakobjecten/1',
        ], 201),
    ]);

    $zaak = Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'form_state_snapshot' => ['values' => [
            'adresVanDeGebouwEn' => [
                ['postcode' => '6411HP', 'huisnummer' => '25'],
            ],
        ]],
    ]);

    $bag = new BagObject(
        id: 'adr-db779ed64b9dfdfeab8bca3ed3f15bd0',
        type: 'adres',
        centroide_ll: 'POINT(5.98 50.88)',
        weergavenaam: 'Geleenstraat 25, 6411HP Heerlen',
        straatnaam: 'Geleenstraat',
        postcode: '6411HP',
        huisnummer: '25',
        gemeentecode: '0917',
        woonplaatsnaam: 'Heerlen',
        nummeraanduiding_id: '0917201313093153',
    );

    // Stub the address lookup so the builder collects a known BAG address.
    $locationService = Mockery::mock(LocatieserverService::class);
    $locationService->shouldReceive('getBagObjectByPostcodeHuisnummer')->andReturn($bag);

    (new AddGeometryZGW($zaak))->handle(
        app(ZaakeigenschappenMap::class),
        new EventLocationGeometryBuilder($locationService),
    );

    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaakobjecten')
        && $request->method() === 'POST'
        && data_get($request->data(), 'objectType') === 'adres'
        && data_get($request->data(), 'objectIdentificatie.identificatie') === '0917201313093153'
        && data_get($request->data(), 'objectIdentificatie.gorOpenbareRuimteNaam') === 'Geleenstraat'
        && data_get($request->data(), 'objectIdentificatie.wplWoonplaatsNaam') === 'Heerlen'
        && data_get($request->data(), 'objectIdentificatie.postcode') === '6411HP');
});
