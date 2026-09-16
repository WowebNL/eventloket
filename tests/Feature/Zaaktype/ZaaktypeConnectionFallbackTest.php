<?php

declare(strict_types=1);

/**
 * The crossing between the ZGW connection a zaak is created on and the ZGW
 * instance the zaaktype belongs to.
 *
 * A municipality with its own ZGW connection keeps its own zaaktype rows, which
 * carry urls from its own catalogus. The runtime connection of that municipality
 * falls back to the main connection whenever the own one cannot be used. Before
 * the fallback covered the zaaktype as well, only the zaak moved: the payload
 * then carried a zaaktype url of one instance to the zaken API of another, which
 * the receiving instance rejects because it does not know that service.
 *
 * These tests walk the two submit steps that decide it (resolve the zaaktype,
 * create the zaak) for each of the three ways the connection falls back.
 */

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\EventForm\Submit\ResolveZaaktype;
use App\EventForm\Submit\Steps\CreateZaakInZGW;
use App\Exceptions\ZaaktypeConnectionMismatchException;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// The main connection of the test environment, and the separate instance a
// municipality connection points at. Both are fictional hosts.
const ZTC_MAIN = 'https://zgw.example.com';
const ZTC_OWN = 'https://gemeente.example.com';

beforeEach(function () {
    // Any request to the municipality's own instance is a leak: on a fallback
    // nothing may be read from or written to it.
    Http::preventStrayRequests();
});

/**
 * A municipality that runs its own ZGW connection, with a mapped own-instance
 * zaaktype and (optionally) the matching main-catalogus row.
 *
 * @return array{0: Municipality, 1: Zaaktype, 2: ?Zaaktype}
 */
function ztcSetup(array $connectionAttributes = [], bool $withMainRow = true): array
{
    $municipality = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    MunicipalityZgwConnection::factory()->create(array_merge([
        'municipality_id' => $municipality->id,
    ], $connectionAttributes));

    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'OWN-1',
    ]));

    // What the koppeling sync writes as soon as a zaaktype is coupled: an active
    // row on the municipality's own connection, carrying its own catalogus url.
    $own = Zaaktype::factory()->create([
        'name' => 'Eigen evenementenvergunning',
        'identificatie' => 'OWN-1',
        'connection' => "gemeente_{$municipality->id}",
        'zgw_zaaktype_url' => ZTC_OWN.'/catalogi/api/v1/zaaktypen/own',
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $municipality->id,
        'is_active' => true,
    ]);

    $main = $withMainRow ? Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'identificatie' => 'MAIN-1',
        'connection' => 'main',
        'zgw_zaaktype_url' => ZTC_MAIN.'/catalogi/api/v1/zaaktypen/main',
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => null,
        'is_active' => true,
    ]) : null;

    return [$municipality, $own, $main];
}

/**
 * The same setup, brought into the state of one of the three triggers that make
 * the runtime connection fall back to main.
 *
 * "Nooit geactiveerd" and "gedeactiveerd" reach the same runtime state (no
 * activation stamp) along different routes: the first is a koppeling that is
 * being configured, the second one that was live and was switched off, by hand
 * or automatically after a change to a critical field. They are listed
 * separately because they are separate situations for a beheerder.
 *
 * @return array{0: Municipality, 1: Zaaktype, 2: ?Zaaktype}
 */
function ztcSetupForTrigger(string $trigger): array
{
    if ($trigger === 'deactivated') {
        $setup = ztcSetup(['activated_at' => now(), 'last_verified_at' => now()]);
        MunicipalityZgwConnection::query()->firstOrFail()->update(['activated_at' => null]);

        return $setup;
    }

    return ztcSetup(match ($trigger) {
        'invalid_config' => ['activated_at' => now(), 'last_verified_at' => now(), 'client_secret' => 'te-kort'],
        default => ['activated_at' => null],
    });
}

function ztcState(): FormState
{
    return new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        'watIsDeNaamVanHetEvenementVergunning' => 'Testevenement',
    ]);
}

/**
 * Fake the main instance: a catalogi lookup that answers with the version valid
 * today, and a zaken endpoint that accepts the creation.
 *
 * The catalogus only knows its own zaaktype: a lookup for an identificatie from
 * the municipality's own catalogus finds nothing, exactly as a real main
 * catalogus would.
 */
function ztcFakeMain(string $versionUrl): void
{
    Http::fake([
        ZTC_MAIN.'/catalogi/api/v1/zaaktypen*' => function (Request $request) use ($versionUrl) {
            $results = str_contains($request->url(), 'identificatie=MAIN-1')
                ? [['url' => $versionUrl, 'identificatie' => 'MAIN-1']]
                : [];

            return Http::response([
                'count' => count($results),
                'next' => null,
                'previous' => null,
                'results' => $results,
            ], 200);
        },
        ZTC_MAIN.'/zaken/api/v1/zaken' => Http::response([
            'url' => ZTC_MAIN.'/zaken/api/v1/zaken/new-1',
            'uuid' => 'new-1',
            'identificatie' => 'ZAAK-2026-0001',
            'zaaktype' => $versionUrl,
            'omschrijving' => 'Testevenement',
            'startdatum' => now()->toDateString(),
            'registratiedatum' => now()->toDateString(),
            'bronorganisatie' => '820151130',
            'zaakgeometrie' => null,
        ], 201),
    ]);
}

/**
 * All three triggers land the aanvraag on the main connection, with a zaaktype
 * that belongs to main. Each of them used to send the municipality's own
 * zaaktype url to the main zaken API.
 */
test('een terugval naar main maakt de zaak aan op main, met een main-zaaktype', function (string $trigger) {
    [, , $main] = ztcSetupForTrigger($trigger);
    $versionUrl = ZTC_MAIN.'/catalogi/api/v1/zaaktypen/main-valid-today';
    ztcFakeMain($versionUrl);

    $state = ztcState();
    $zaaktype = app(ResolveZaaktype::class)->forState($state);
    $zaak = app(CreateZaakInZGW::class)->execute($state, $zaaktype);

    expect($zaaktype->id)->toBe($main->id)
        ->and($zaak->zaaktype)->toBe($versionUrl)
        ->and($zaak->identificatie)->toBe('ZAAK-2026-0001');

    // The payload carries a zaaktype of the instance it is posted to.
    Http::assertSent(fn ($request) => $request->url() === ZTC_MAIN.'/zaken/api/v1/zaken'
        && $request->method() === 'POST'
        && $request['zaaktype'] === $versionUrl);

    // Nothing was read from the municipality's own instance (preventStrayRequests
    // would already have failed on it; this states the intent).
    Http::assertNotSent(fn ($request) => str_starts_with($request->url(), ZTC_OWN));
})->with([
    'nooit geactiveerd' => ['never_activated'],
    'gedeactiveerd' => ['deactivated'],
    'actief met onbruikbare config' => ['invalid_config'],
]);

test('een zaak die door de terugval op main landt is terug te vinden in de log', function () {
    Log::spy();

    ztcSetup(['activated_at' => null]);
    $versionUrl = ZTC_MAIN.'/catalogi/api/v1/zaaktypen/main-valid-today';
    ztcFakeMain($versionUrl);

    $state = ztcState();
    $municipalityId = Municipality::query()->firstOrFail()->id;

    app(CreateZaakInZGW::class)->execute($state, app(ResolveZaaktype::class)->forState($state));

    // Gemeente, the connection that was meant, the reason, and the zaak itself:
    // enough to find every aanvraag that landed on the wrong instance.
    Log::shouldHaveReceived('warning')
        ->withArgs(function (...$args) use ($municipalityId): bool {
            $context = $args[1] ?? [];

            return is_string($args[0] ?? null)
                && str_contains($args[0], 'created on the main ZGW connection')
                && ($context['municipality_id'] ?? null) === $municipalityId
                && ($context['connection'] ?? null) === 'main'
                && ($context['intended_connection'] ?? null) === "gemeente_{$municipalityId}"
                && ($context['reason'] ?? null) === 'not_activated'
                && ($context['zaak_identificatie'] ?? null) === 'ZAAK-2026-0001';
        })
        ->once();
});

test('een bruikbare, actieve koppeling blijft gewoon op de eigen instantie werken', function () {
    [, $own] = ztcSetup(['activated_at' => now()]);
    $versionUrl = ZTC_OWN.'/catalogi/api/v1/zaaktypen/own-valid-today';

    Http::fake([
        ZTC_OWN.'/catalogi/api/v1/zaaktypen*' => Http::response([
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [['url' => $versionUrl, 'identificatie' => 'OWN-1']],
        ], 200),
        ZTC_OWN.'/zaken/api/v1/zaken' => Http::response([
            'url' => ZTC_OWN.'/zaken/api/v1/zaken/new-1',
            'uuid' => 'new-1',
            'identificatie' => 'ZAAK-EIGEN-1',
            'zaaktype' => $versionUrl,
            'omschrijving' => 'Testevenement',
            'startdatum' => now()->toDateString(),
            'registratiedatum' => now()->toDateString(),
            'bronorganisatie' => '820151130',
            'zaakgeometrie' => null,
        ], 201),
    ]);

    $state = ztcState();
    $zaaktype = app(ResolveZaaktype::class)->forState($state);
    $zaak = app(CreateZaakInZGW::class)->execute($state, $zaaktype);

    expect($zaaktype->id)->toBe($own->id)
        ->and($zaak->zaaktype)->toBe($versionUrl);

    Http::assertNotSent(fn ($request) => str_starts_with($request->url(), ZTC_MAIN));
});

test('blijft er na de terugval een mismatch over, dan volgt een leesbare fout en geen zaak', function () {
    // Nothing in the main catalogus to fall back to, so the own-instance row
    // survives the fallback and the guard has to refuse the creation.
    ztcSetup(['activated_at' => null], withMainRow: false);

    Http::fake([
        ZTC_MAIN.'/catalogi/api/v1/zaaktypen*' => Http::response([
            'count' => 0, 'next' => null, 'previous' => null, 'results' => [],
        ], 200),
        ZTC_MAIN.'/zaken/api/v1/zaken' => Http::response([], 201),
    ]);

    $state = ztcState();
    $zaaktype = app(ResolveZaaktype::class)->forState($state);

    expect(fn () => app(CreateZaakInZGW::class)->execute($state, $zaaktype))
        ->toThrow(ZaaktypeConnectionMismatchException::class, 'hoort niet bij de ZGW-koppeling');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});
