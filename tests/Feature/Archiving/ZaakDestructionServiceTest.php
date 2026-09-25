<?php

use App\Exceptions\ZaakConnectionNotDestructibleException;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Archiving\ZaakDestructionService;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

/**
 * Only zaken on our own OpenZaak are destroyed from here. A municipality that
 * runs its own ZGW instance is the zorgdrager there and destroys in that
 * system; the credentials it issues us are not authorised to delete anyway.
 */
function ownInstanceZaak(): Zaak
{
    $municipality = Municipality::factory()->create();

    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $municipality->id,
    ]);

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'connection' => 'gemeente_'.$municipality->id,
        'zgw_zaaktype_url' => 'https://gemeente.example.com/catalogi/api/v1/zaaktypen/1',
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => 'https://gemeente.example.com/zaken/api/v1/zaken/1',
    ]);
}

test('destroying a zaak on a municipality own zgw instance is refused', function () {
    Http::fake();

    $zaak = ownInstanceZaak();

    expect(fn () => app(ZaakDestructionService::class)->destroy($zaak->zgw_zaak_url))
        ->toThrow(ZaakConnectionNotDestructibleException::class);

    Http::assertNothingSent();
});

test('fetching a zaak on a municipality own zgw instance is refused', function () {
    Http::fake();

    $zaak = ownInstanceZaak();

    expect(fn () => app(ZaakDestructionService::class)->fetchZaak($zaak->zgw_zaak_url))
        ->toThrow(ZaakConnectionNotDestructibleException::class);

    Http::assertNothingSent();
});

test('a url with no local zaak that belongs to an own instance host is refused', function () {
    Http::fake();

    // The item's zaak row is gone (a partially completed destruction), so the
    // host index is the only thing left to resolve on.
    ownInstanceZaak()->forceDelete();

    expect(fn () => app(ZaakDestructionService::class)->destroy('https://gemeente.example.com/zaken/api/v1/zaken/99'))
        ->toThrow(ZaakConnectionNotDestructibleException::class);

    Http::assertNothingSent();
});

test('a zaak on our own openzaak is destructible', function () {
    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
    ]);

    expect(app(ZaakDestructionService::class)->isDestructible($zaak->zgw_zaak_url))->toBeTrue();
});

test('a shared document whose relations sit on the second page is kept', function () {
    $base = ZgwHttpFake::$baseUrl;
    $zaakUrl = $base.'/zaken/api/v1/zaken/1';
    $sharedDocument = $base.'/documenten/api/v1/enkelvoudiginformatieobjecten/d1';
    $zaakinformatieobject = $base.'/zaken/api/v1/zaakinformatieobjecten/zio1';

    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $base.'/catalogi/api/v1/zaaktypen/1',
    ]);

    Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    $secondPage = $base.'/documenten/api/v1/objectinformatieobjecten?page=2';

    Http::fake(function ($request) use ($base, $zaakUrl, $zaakinformatieobject, $sharedDocument, $secondPage) {
        $url = $request->url();

        if ($request->method() === 'DELETE') {
            return Http::response(null, 204);
        }

        return match (true) {
            str_contains($url, '/besluiten/api/v1/besluiten') => Http::response(ZgwHttpFake::envelope([]), 200),
            str_contains($url, '/zaken/api/v1/zaakinformatieobjecten') => Http::response(ZgwHttpFake::envelope([
                ['url' => $zaakinformatieobject, 'informatieobject' => $sharedDocument, 'zaak' => $zaakUrl],
            ]), 200),
            // First page empty with a next link; the relation that makes this
            // document shared only shows up on page two.
            $url === $secondPage => Http::response(ZgwHttpFake::envelope([
                ['url' => $base.'/documenten/api/v1/objectinformatieobjecten/oio-other', 'object' => $base.'/zaken/api/v1/zaken/other'],
            ]), 200),
            str_contains($url, '/documenten/api/v1/objectinformatieobjecten') => Http::response([
                'count' => 1,
                'next' => $secondPage,
                'previous' => null,
                'results' => [],
            ], 200),
            default => Http::response(ZgwHttpFake::envelope([]), 200),
        };
    });

    $result = app(ZaakDestructionService::class)->destroy($zaakUrl);

    expect($result['skipped_documents'])->toBe([$sharedDocument]);

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === $sharedDocument);
});
