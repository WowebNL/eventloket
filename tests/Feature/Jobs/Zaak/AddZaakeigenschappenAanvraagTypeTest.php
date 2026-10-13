<?php

declare(strict_types=1);

/**
 * The aanvraagtype as an optional zaakeigenschap.
 *
 * When several kinds of aanvraag share one zaaktype, the zaaksysteem can only
 * tell them apart through an eigenschap. Whether a municipality wants that is
 * up to its catalogus: the eigenschap is written when the zaaktype defines it,
 * under the naam the koppeling maps it to, and skipped otherwise.
 */

use App\Enums\ZaaktypeRole;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Jobs\Zaak\AddZaakeigenschappenZGW;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

/**
 * @param  list<array<string, string>>  $catalogusEigenschappen
 */
function fakeZaakeigenschappenCatalogus(array $catalogusEigenschappen): void
{
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/aanvraagtype-1';

    Http::fake([
        $zaakUrl.'*' => Http::response([
            'url' => $zaakUrl,
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/gedeeld',
            'identificatie' => 'ZAAK-SYN-1',
            '_expand' => ['eigenschappen' => []],
        ], 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => Http::response(ZgwHttpFake::envelope($catalogusEigenschappen), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/aanvraagtype-1/zaakeigenschappen' => Http::response(['url' => $zaakUrl.'/zaakeigenschappen/1'], 201),
        '*' => Http::response([], 200),
    ]);
}

function zaakVoorAanvraagtype(array $eigenschapMap = []): Zaak
{
    $municipality = Municipality::factory()->create();

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Melding,
        'zaaktype_identificatie' => 'SYN-GEDEELD',
        'eigenschap_map' => $eigenschapMap,
    ]);

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'identificatie' => 'SYN-GEDEELD',
        'is_active' => true,
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'role' => ZaaktypeRole::Melding,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/aanvraagtype-1',
        'form_state_snapshot' => ['values' => [], 'system' => []],
    ]);
}

/** @return list<string> the waarden POSTed for the given eigenschap url */
function geschrevenWaarden(string $eigenschapUrl): array
{
    return Http::recorded(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/zaakeigenschappen')
        && $request['eigenschap'] === $eigenschapUrl)
        ->map(fn (array $pair) => $pair[0]['waarde'])
        ->values()
        ->all();
}

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
});

it('writes the aanvraagtype under the naam the koppeling maps it to', function () {
    $eigenschapUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/soort';
    fakeZaakeigenschappenCatalogus([['url' => $eigenschapUrl, 'naam' => 'Soort aanvraag']]);

    $zaak = zaakVoorAanvraagtype(['aanvraag_type' => 'Soort aanvraag']);

    (new AddZaakeigenschappenZGW($zaak))->handle(app(ZaakeigenschappenMap::class));

    expect(geschrevenWaarden($eigenschapUrl))->toBe(['Melding']);
});

it('skips the aanvraagtype when the catalogus of the zaaktype does not define it', function () {
    fakeZaakeigenschappenCatalogus([]);

    $zaak = zaakVoorAanvraagtype();

    (new AddZaakeigenschappenZGW($zaak))->handle(app(ZaakeigenschappenMap::class));

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/zaakeigenschappen'));
});
