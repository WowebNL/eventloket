<?php

declare(strict_types=1);

use App\Jobs\Zaak\AddGlobaleLocatieZGW;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

/**
 * A zaak with the composed location names on its reference data and, optionally,
 * the BAG addresses of the aanvraag in its form snapshot.
 *
 * @param  list<array<string, mixed>>|null  $adressen  rows of the adresVanDeGebouwEn repeater
 */
function zaakWithLocaties(?string $locaties, ?array $adressen = null): Zaak
{
    $zaakUrl = ZgwHttpFake::fakeSingleZaak();

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakobjecten/99',
        ], 201),
    ]);

    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create();

    return Zaak::factory()->create([
        'zgw_zaak_url' => $zaakUrl,
        'zaaktype_id' => $zaaktype->id,
        'form_state_snapshot' => $adressen === null ? [] : ['values' => ['adresVanDeGebouwEn' => $adressen]],
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->toIso8601String(),
            eind_evenement: now()->addDay()->toIso8601String(),
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            locaties_evenement: $locaties,
        ),
    ]);
}

/**
 * One row of the adresVanDeGebouwEn repeater.
 *
 * @return array<string, mixed>
 */
function gebouwRow(string $naam, string $straat, string $huisnummer, string $postcode, string $plaats): array
{
    return [
        'naamVanDeLocatieGebouw' => $naam,
        'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
            'postcode' => $postcode,
            'huisnummer' => $huisnummer,
            'straatnaam' => $straat,
            'woonplaatsnaam' => $plaats,
        ],
    ];
}

test('registers the BAG address of the event instead of the location names', function () {
    $zaak = zaakWithLocaties('Marktplein', [
        gebouwRow('Marktplein', 'Coriovallumstraat', '32', '6411 CD', 'Heerlen'),
    ]);

    dispatch(new AddGlobaleLocatieZGW($zaak));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/zaken/api/v1/zaakobjecten')
            && $request->method() === 'POST'
            && data_get($request->data(), 'objectTypeOverige') === 'GlobaleLocatie'
            && data_get($request->data(), 'objectIdentificatie.overigeData.naam') === 'Coriovallumstraat 32, 6411CD Heerlen';
    });
});

test('registers every BAG address of the event, comma separated', function () {
    $zaak = zaakWithLocaties('Marktplein, Vrijthof', [
        gebouwRow('Marktplein', 'Coriovallumstraat', '32', '6411CD', 'Heerlen'),
        gebouwRow('Vrijthof', 'Vrijthof', '1', '6221AB', 'Maastricht'),
    ]);

    dispatch(new AddGlobaleLocatieZGW($zaak));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaakobjecten')
        && $request->method() === 'POST'
        && data_get($request->data(), 'objectIdentificatie.overigeData.naam')
            === 'Coriovallumstraat 32, 6411CD Heerlen, Vrijthof 1, 6221AB Maastricht');
});

test('falls back to the composed location names when the event has no BAG address', function () {
    // An outdoor event or a route: no address, so the names from the form stand.
    $zaak = zaakWithLocaties('Marktplein, Hoofdstraat');

    dispatch(new AddGlobaleLocatieZGW($zaak));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/zaken/api/v1/zaakobjecten')
            && $request->method() === 'POST'
            && data_get($request->data(), 'objectType') === 'overige'
            && data_get($request->data(), 'objectTypeOverige') === 'GlobaleLocatie'
            // ZGW ObjectOverige requires the identification under overigeData.
            && data_get($request->data(), 'objectIdentificatie.overigeData.naam') === 'Marktplein, Hoofdstraat';
    });
});

test('sends overigeData as a bare string on a OneGround connection', function () {
    // OneGround (RX Mission) deviates from the ZGW standard: it stores/expects
    // overigeData as a plain string, not the standard free-form object. The zaak
    // here resolves to the "main" connection, so we flag main as OneGround.
    config(['zgw.connections.main.is_oneground' => true]);

    $zaak = zaakWithLocaties('Marktplein, Hoofdstraat');

    dispatch(new AddGlobaleLocatieZGW($zaak));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/zaken/api/v1/zaakobjecten')
            && $request->method() === 'POST'
            && data_get($request->data(), 'objectTypeOverige') === 'GlobaleLocatie'
            && data_get($request->data(), 'objectIdentificatie.overigeData') === 'Marktplein, Hoofdstraat';
    });
});

test('does nothing when there are no location names', function () {
    $zaak = zaakWithLocaties(null);

    dispatch(new AddGlobaleLocatieZGW($zaak));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaakobjecten') && $request->method() === 'POST');
});
