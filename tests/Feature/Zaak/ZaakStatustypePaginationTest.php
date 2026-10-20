<?php

use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

/**
 * The statustype of a zaak is looked up in the full statustypen list of its connection. A
 * catalogus with many zaaktypen returns that list over several pages, so the lookup has to
 * see every page, not only the last one.
 */
beforeEach(function () {
    $statustypen = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen';

    $statustype = fn (int $id, string $omschrijving): array => [
        'url' => "{$statustypen}/{$id}",
        'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
        'omschrijving' => $omschrijving,
        'volgnummer' => $id,
        'isEindstatus' => false,
    ];

    Http::preventStrayRequests();
    Http::fake([
        "{$statustypen}*" => function (Request $request) use ($statustypen, $statustype) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (($query['page'] ?? '1') === '2') {
                return Http::response([
                    'count' => 2,
                    'next' => null,
                    'previous' => $statustypen,
                    'results' => [$statustype(2, 'In behandeling')],
                ]);
            }

            return Http::response([
                'count' => 2,
                'next' => "{$statustypen}?page=2",
                'previous' => null,
                'results' => [$statustype(1, 'Ontvangen')],
            ]);
        },
    ]);

    $municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
});

function zaakWithStatustype(Zaaktype $zaaktype, string $statustypeUrl): Zaak
{
    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->toDateTimeString(),
            eind_evenement: now()->addDay()->toDateTimeString(),
            registratiedatum: now()->toDateTimeString(),
            status_name: 'Ontvangen',
            statustype_url: $statustypeUrl,
            risico_classificatie: 'A',
            naam_locatie_eveneme: 'Test locatie',
            naam_evenement: 'Test event'
        ),
    ]);
}

it('resolves a statustype from the first page of a paginated statustypen list', function () {
    $zaak = zaakWithStatustype($this->zaaktype, ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1');

    expect($zaak->statustype)->not->toBeNull()
        ->and($zaak->statustype->omschrijving)->toBe('Ontvangen');
});

it('resolves a statustype from the last page of a paginated statustypen list', function () {
    $zaak = zaakWithStatustype($this->zaaktype, ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/2');

    expect($zaak->statustype)->not->toBeNull()
        ->and($zaak->statustype->omschrijving)->toBe('In behandeling');
});
