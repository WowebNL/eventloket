<?php

/**
 * Tests voor SetInitialStatusZGW.
 *
 * De job moet het statustype met het laagste volgnummer (= 1, de
 * ontvangstbevestiging) als initiële status op de ZGW-zaak zetten.
 *
 * Gevallen:
 *   - Happy path: statustype met volgnummer 1 wordt via POST naar
 *     /zaken/api/v1/statussen verstuurd.
 *   - Geen zgw_zaak_url: job doet niets.
 *   - Geen statustypen gevonden: job slaat over zonder exception.
 */

use App\Jobs\Zaak\SetInitialStatusZGW;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

function zaakMetZgwUrl(?string $zgwZaakUrl = null): Zaak
{
    $muni = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $muni->id,
        'is_active' => true,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl ?? ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/test-uuid',
    ]);
}

test('zet statustype met volgnummer 1 als initiële status op de ZGW-zaak', function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $zaak = zaakMetZgwUrl();

    ZgwHttpFake::fakeStatustypen();

    $statussenUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen';
    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1', 'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1', 'omschrijving' => 'Ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/2', 'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1', 'omschrijving' => 'In behandeling', 'volgnummer' => 2, 'isEindstatus' => false],
        ], 200),
        $statussenUrl => Http::response(['url' => $statussenUrl.'/new'], 201),
    ]);

    (new SetInitialStatusZGW($zaak))->handle(app(\Woweb\Openzaak\Openzaak::class));

    Http::assertSent(function ($request) use ($zaak) {
        return str_contains($request->url(), '/zaken/api/v1/statussen')
            && $request->method() === 'POST'
            && $request['zaak'] === $zaak->zgw_zaak_url
            && str_contains($request['statustype'], '/statustypen/1');
    });
});

test('geen zgw_zaak_url: job doet niets en stuurt geen request', function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $zaak = zaakMetZgwUrl(null);
    $zaak->zgw_zaak_url = null;
    $zaak->save();

    Http::fake();

    (new SetInitialStatusZGW($zaak))->handle(app(\Woweb\Openzaak\Openzaak::class));

    Http::assertNothingSent();
});

test('geen statustypen gevonden: job slaat over zonder exception', function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $zaak = zaakMetZgwUrl();

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response([], 200),
        ZgwHttpFake::$baseUrl.'*' => Http::response([], 200),
    ]);

    expect(fn () => (new SetInitialStatusZGW($zaak))->handle(app(\Woweb\Openzaak\Openzaak::class)))
        ->not->toThrow(Throwable::class);

    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/zaken/api/v1/statussen'));
});
