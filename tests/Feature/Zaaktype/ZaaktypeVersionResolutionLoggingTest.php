<?php

declare(strict_types=1);

/**
 * When the zaaktype version valid on the creation date cannot be resolved, the
 * zaak is still created against the stored version url. That url may be closed,
 * still a concept, or hosted by another instance than the one the zaak is
 * created in, so the create can be rejected with a validation error that says
 * nothing about the resolution that preceded it. These tests pin that both ways
 * of failing to resolve leave a record behind.
 */

use App\EventForm\State\FormState;
use App\EventForm\Submit\Steps\CreateZaakInZGW;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

/**
 * Fake a successful zaak create so the step completes after the version
 * resolution under test.
 */
function fakeZaakCreate(string $storedUrl): void
{
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/new-1',
            'uuid' => 'new-1',
            'identificatie' => 'ZAAK-1',
            'zaaktype' => $storedUrl,
            'omschrijving' => 'Test',
            'startdatum' => now()->toDateString(),
            'registratiedatum' => now()->toDateString(),
            'bronorganisatie' => '820151130',
            'zaakgeometrie' => null,
        ], 201),
    ]);
}

test('an empty version lookup is recorded before the stored url is used', function () {
    $storedUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/stored';

    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => 'EVG-TEST',
        'zgw_zaaktype_url' => $storedUrl,
    ]);

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen*' => Http::response(ZgwHttpFake::envelope([]), 200),
    ]);
    fakeZaakCreate($storedUrl);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($zaaktype): bool {
            return str_contains($message, 'could not resolve the zaaktype version')
                && $context['zaaktype_id'] === $zaaktype->id
                && $context['connection'] === 'main'
                && $context['identificatie'] === 'EVG-TEST'
                && str_contains($context['reason'], 'no definitief version valid today');
        });

    $oz = app(CreateZaakInZGW::class)->execute(
        new FormState(values: ['watIsDeNaamVanHetEvenementVergunning' => 'Test']),
        $zaaktype,
    );

    expect($oz->zaaktype)->toBe($storedUrl);
});

test('a failing version lookup is recorded before the stored url is used', function () {
    $storedUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/stored';

    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => 'EVG-TEST',
        'zgw_zaaktype_url' => $storedUrl,
    ]);

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen*' => Http::response(['detail' => 'nope'], 500),
    ]);
    fakeZaakCreate($storedUrl);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'could not resolve the zaaktype version')
                && $context['identificatie'] === 'EVG-TEST'
                && $context['reason'] !== '';
        });

    $oz = app(CreateZaakInZGW::class)->execute(
        new FormState(values: ['watIsDeNaamVanHetEvenementVergunning' => 'Test']),
        $zaaktype,
    );

    expect($oz->zaaktype)->toBe($storedUrl);
});

test('a resolved version is used without a warning', function () {
    $storedUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/stored';
    $versionUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/valid-today';

    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => 'EVG-TEST',
        'zgw_zaaktype_url' => $storedUrl,
    ]);

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $versionUrl, 'identificatie' => 'EVG-TEST'],
        ]), 200),
    ]);
    fakeZaakCreate($versionUrl);

    Log::shouldReceive('warning')->never();

    $oz = app(CreateZaakInZGW::class)->execute(
        new FormState(values: ['watIsDeNaamVanHetEvenementVergunning' => 'Test']),
        $zaaktype,
    );

    expect($oz->zaaktype)->toBe($versionUrl);
});
