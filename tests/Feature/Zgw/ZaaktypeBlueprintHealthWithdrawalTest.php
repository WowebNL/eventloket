<?php

declare(strict_types=1);

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Services\Zgw\ZaaktypeBlueprintHealth;
use App\Services\Zgw\ZgwConnectionResolver;
use App\ValueObjects\ZGW\BlueprintFinding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

/**
 * Fake a catalogus that lacks both an eind-statustype (no isEindstatus) and an
 * Ingetrokken resultaattype, so those two blueprint slots would be reported as
 * Missing unless withdrawal suppression kicks in.
 */
function fakeCatalogusWithoutEindOrIngetrokken(string $base): void
{
    Http::fake([
        "{$base}/catalogi/api/v1/zaaktypen*" => Http::response(ZgwHttpFake::envelope([
            ['identificatie' => 'EVT-1', 'omschrijving' => 'Evenementenvergunning', 'url' => "{$base}/catalogi/api/v1/zaaktypen/1"],
        ])),
        "{$base}/catalogi/api/v1/statustypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Ontvangen', 'volgnummer' => 1],
        ])),
        "{$base}/catalogi/api/v1/roltypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Aanvrager', 'omschrijvingGeneriek' => 'initiator'],
        ])),
        "{$base}/catalogi/api/v1/resultaattypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Verleend', 'omschrijvingGeneriek' => 'Verleend'],
        ])),
        "{$base}/catalogi/api/v1/zaaktype-informatieobjecttypen*" => Http::response(ZgwHttpFake::envelope([
            ['informatieobjecttype' => "{$base}/catalogi/api/v1/informatieobjecttypen/1"],
        ])),
        "{$base}/catalogi/api/v1/informatieobjecttypen/1" => Http::response([
            'url' => "{$base}/catalogi/api/v1/informatieobjecttypen/1",
            'omschrijving' => 'Aanvraag',
        ]),
        "{$base}/catalogi/api/v1/eigenschappen*" => Http::response(ZgwHttpFake::envelope([
            ['naam' => 'intern_zaaknummer'],
        ])),
    ]);
}

/**
 * @return list<string>
 */
function healthSlots(Municipality $municipality): array
{
    $connectionName = app(ZgwConnectionResolver::class)->forManagement($municipality);

    return array_map(
        fn (BlueprintFinding $finding): string => $finding->slot,
        app(ZaaktypeBlueprintHealth::class)->check($connectionName, 'EVT-1', null),
    );
}

beforeEach(function () {
    Cache::flush();
    $this->municipality = Municipality::factory()->create();
});

test('withdrawal disabled suppresses eind-statustype and ingetrokken-resultaattype findings', function () {
    MunicipalityZgwConnection::factory()->create([
        'municipality_id' => $this->municipality->id,
        'allow_organiser_withdrawal' => false,
    ]);
    fakeCatalogusWithoutEindOrIngetrokken('https://gemeente.example.com');

    $slots = healthSlots($this->municipality);

    expect($slots)->not->toContain('eind_statustype')
        ->and($slots)->not->toContain('ingetrokken_resultaattype');
});

test('withdrawal enabled still reports the missing eind-statustype and ingetrokken-resultaattype', function () {
    MunicipalityZgwConnection::factory()->create([
        'municipality_id' => $this->municipality->id,
        'allow_organiser_withdrawal' => true,
    ]);
    fakeCatalogusWithoutEindOrIngetrokken('https://gemeente.example.com');

    $slots = healthSlots($this->municipality);

    expect($slots)->toContain('eind_statustype')
        ->and($slots)->toContain('ingetrokken_resultaattype');
});
