<?php

/**
 * UpdateInitiatorZGW zet de initiator-rol op de ZGW-zaak op basis van het
 * initiator-blok in de FormState-snapshot.
 *
 * Belangrijkste gedrag dat hier wordt afgedekt: voor een organisatie met een
 * KvK-nummer sturen we als bedrijfsidentificatie uitsluitend `kvkNummer`, voor
 * elke connectie (OpenZaak inbegrepen). `annIdentificatie` wordt bewust
 * weggelaten omdat niet elke ZGW-instantie dat veld accepteert.
 */

use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Jobs\Zaak\UpdateInitiatorZGW;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

function zaakMetInitiator(array $values): Zaak
{
    $muni = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $muni->id,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/abc-123',
        'form_state_snapshot' => ['values' => $values],
    ]);
}

function fakeZaakRoltypenEnRollen(): void
{
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/abc-123';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen/1', 'omschrijvingGeneriek' => 'initiator'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen' => Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen/1'], 201),
        $zaakUrl.'*' => Http::response([
            'url' => $zaakUrl,
            'uuid' => 'abc-123',
            'identificatie' => 'ZAAK-123',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
            'omschrijving' => 'Test',
            'startdatum' => '2026-06-26',
            'registratiedatum' => '2026-06-26',
            'einddatum' => null,
            'einddatumGepland' => null,
            'uiterlijkeEinddatumAfdoening' => null,
            'bronorganisatie' => '820151130',
            'zaakgeometrie' => null,
        ], 200),
    ]);
}

test('stuurt voor een organisatie alleen kvkNummer als bedrijfsidentificatie', function () {
    $zaak = zaakMetInitiator([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'watIsDeNaamVanUwOrganisatie' => 'Acme BV',
    ]);

    fakeZaakRoltypenEnRollen();

    (new UpdateInitiatorZGW($zaak))->handle(app(ZaakeigenschappenMap::class));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/zaken/api/v1/rollen') || $request->method() !== 'POST') {
            return false;
        }

        $identificatie = $request->data()['betrokkeneIdentificatie'] ?? [];

        return $request->data()['betrokkeneType'] === 'niet_natuurlijk_persoon'
            && ($identificatie['kvkNummer'] ?? null) === '12345678'
            && ! array_key_exists('annIdentificatie', $identificatie)
            && ($identificatie['statutaireNaam'] ?? null) === 'Acme BV';
    });
});

test('zonder zgw_zaak_url gebeurt er niets', function () {
    $zaak = zaakMetInitiator([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
    ]);
    $zaak->zgw_zaak_url = null;
    $zaak->save();

    Http::fake();

    (new UpdateInitiatorZGW($zaak))->handle(app(ZaakeigenschappenMap::class));

    Http::assertNothingSent();
});
