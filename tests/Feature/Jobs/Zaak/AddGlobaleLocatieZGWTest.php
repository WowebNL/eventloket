<?php

declare(strict_types=1);

use App\Jobs\Zaak\AddGlobaleLocatieZGW;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

function zaakWithLocaties(?string $locaties): Zaak
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

test('registers a GlobaleLocatie zaakobject with the composed location names', function () {
    $zaak = zaakWithLocaties('Marktplein, Hoofdstraat');

    dispatch(new AddGlobaleLocatieZGW($zaak));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/zaken/api/v1/zaakobjecten')
            && $request->method() === 'POST'
            && data_get($request->data(), 'objectType') === 'overige'
            && data_get($request->data(), 'objectTypeOverige') === 'GlobaleLocatie'
            && data_get($request->data(), 'objectIdentificatie.naam') === 'Marktplein, Hoofdstraat';
    });
});

test('does nothing when there are no location names', function () {
    $zaak = zaakWithLocaties(null);

    dispatch(new AddGlobaleLocatieZGW($zaak));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaakobjecten') && $request->method() === 'POST');
});
