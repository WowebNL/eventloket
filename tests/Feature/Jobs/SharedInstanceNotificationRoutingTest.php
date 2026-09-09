<?php

declare(strict_types=1);

use App\Exceptions\UnresolvedNotificationConnectionException;
use App\Jobs\DocumentNotificationReceived;
use App\Jobs\Zaak\BesluitNotificationReceived;
use App\Jobs\ZaaktypeNotificationReceived;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaaktypeRefresher;
use App\ValueObjects\OpenNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Routing of incoming notifications when several activated connections are
 * configured against one ZGW instance, each with its own credentials.
 *
 * Which connection a read actually ran on is asserted on the credentials that
 * went out: the client_id in the signed request token identifies the connection
 * beyond doubt.
 */
beforeEach(function () {
    Cache::flush();
    Notification::fake();
});

const RSIN_A = '111111110';
const RSIN_B = '222222220';
const SHARED = 'https://shared-instance.example.com';

/**
 * Two activated connections against the same instance, with different RSINs and
 * different credentials.
 *
 * @return array{0: Municipality, 1: Municipality}
 */
function twoTenantsOnOneInstance(): array
{
    $a = Municipality::factory()->create();
    $b = Municipality::factory()->create();

    MunicipalityZgwConnection::factory()->for($a)->active()->create(tenantConnection(RSIN_A, 'client-a'));
    MunicipalityZgwConnection::factory()->for($b)->active()->create(tenantConnection(RSIN_B, 'client-b'));

    return [$a, $b];
}

/**
 * @return array<string, mixed>
 */
function tenantConnection(string $rsin, string $clientId): array
{
    return [
        'zaken_url' => SHARED.'/zaken/api/v1/',
        'catalogi_url' => SHARED.'/catalogi/api/v1/',
        'documenten_url' => SHARED.'/documenten/api/v1/',
        'besluiten_url' => SHARED.'/besluiten/api/v1/',
        'autorisaties_url' => SHARED.'/autorisaties/api/v1/',
        'notificaties_url' => SHARED.'/notificaties/api/v1/',
        'allowed_hosts' => [],
        'bronorganisatie_rsin' => $rsin,
        'client_id' => $clientId,
        'client_secret' => $clientId.'-secret-at-least-32-bytes-long',
        'user_id' => $clientId,
    ];
}

/**
 * @param  array<string, string>  $kenmerken
 */
function tenantNotification(string $kanaal, string $resource, string $hoofdObject, array $kenmerken = [], string $actie = 'create'): OpenNotification
{
    return new OpenNotification(
        actie: $actie,
        kanaal: $kanaal,
        resource: $resource,
        hoofdObject: $hoofdObject,
        resourceUrl: $hoofdObject,
        aanmaakdatum: now()->toIso8601String(),
        kenmerken: $kenmerken,
    );
}

/**
 * The client_id inside the signed token of a recorded request: which connection
 * it was sent as.
 */
function requestClientId(Request $request): ?string
{
    $authorization = $request->header('Authorization')[0] ?? '';
    $segments = explode('.', str_replace('Bearer ', '', $authorization));

    if (count($segments) !== 3) {
        return null;
    }

    $payload = strtr($segments[1], '-_', '+/');
    $claims = json_decode((string) base64_decode(str_pad($payload, intdiv(strlen($payload) + 3, 4) * 4, '=')), true);

    return is_array($claims) && isset($claims['client_id']) ? (string) $claims['client_id'] : null;
}

/**
 * The client_ids used for every request to the given url.
 *
 * @return list<string|null>
 */
function clientIdsUsedFor(string $url): array
{
    $used = [];

    Http::recorded(function (Request $request) use ($url, &$used): bool {
        if (str_starts_with($request->url(), $url)) {
            $used[] = requestClientId($request);
        }

        return true;
    });

    return $used;
}

/**
 * A document on the shared instance, linked to a zaak of the given municipality.
 */
function fakeSharedDocument(Municipality $owner, array $responses = []): string
{
    $documentUrl = SHARED.'/documenten/api/v1/enkelvoudiginformatieobjecten/1';
    $zaakUrl = SHARED.'/zaken/api/v1/zaken/1';

    $zaaktype = Zaaktype::factory()->for($owner)->create(['connection' => "gemeente_{$owner->id}"]);
    Zaak::factory()->for($zaaktype)->create(['zgw_zaak_url' => $zaakUrl]);

    Http::fake(array_merge([
        $documentUrl => Http::response([
            'url' => $documentUrl,
            'identificatie' => 'DOC-1',
            'titel' => 'Document',
            'vertrouwelijkheidaanduiding' => 'openbaar',
            'auteur' => 'Behandelaar',
            'versie' => 1,
            'bestandsnaam' => 'document.pdf',
            'inhoud' => 'x',
            'beschrijving' => '',
            'informatieobjecttype' => SHARED.'/catalogi/api/v1/informatieobjecttypen/1',
            'formaat' => 'application/pdf',
            'locked' => false,
            'creatiedatum' => now()->toDateString(),
            'status' => 'definitief',
        ], 200),
        SHARED.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [[
                'url' => SHARED.'/zaken/api/v1/zaakinformatieobjecten/1',
                'zaak' => $zaakUrl,
                'informatieobject' => $documentUrl,
            ]],
        ], 200),
        SHARED.'/besluiten/api/v1/besluitinformatieobjecten*' => Http::response([
            'count' => 0, 'next' => null, 'previous' => null, 'results' => [],
        ], 200),
    ], $responses));

    return $documentUrl;
}

test('a document notification on a shared instance is read with the connection its organisation kenmerk points at', function () {
    [, $b] = twoTenantsOnOneInstance();
    $documentUrl = fakeSharedDocument($b);

    dispatch(new DocumentNotificationReceived(
        tenantNotification('documenten', 'enkelvoudiginformatieobject', $documentUrl, ['bronorganisatie' => RSIN_B]),
        true,
    ));

    expect(clientIdsUsedFor($documentUrl))->toBe(['client-b']);
});

test('a document notification for an organisation we do not serve is skipped without any read', function () {
    [, $b] = twoTenantsOnOneInstance();
    $documentUrl = fakeSharedDocument($b);

    dispatch(new DocumentNotificationReceived(
        tenantNotification('documenten', 'enkelvoudiginformatieobject', $documentUrl, ['bronorganisatie' => '999999990']),
        true,
    ));

    expect(clientIdsUsedFor($documentUrl))->toBe([]);
});

test('a document notification without an organisation kenmerk falls back to trying each connection', function () {
    [$a, $b] = twoTenantsOnOneInstance();
    $documentUrl = SHARED.'/documenten/api/v1/enkelvoudiginformatieobjecten/1';

    // The first candidate may not read the document, the second may.
    $attempt = 0;
    fakeSharedDocument($b, [
        $documentUrl => function () use (&$attempt) {
            $attempt++;

            return $attempt === 1
                ? Http::response(['detail' => 'not allowed'], 403)
                : Http::response([
                    'url' => SHARED.'/documenten/api/v1/enkelvoudiginformatieobjecten/1',
                    'identificatie' => 'DOC-1',
                    'titel' => 'Document',
                    'vertrouwelijkheidaanduiding' => 'openbaar',
                    'auteur' => 'Behandelaar',
                    'versie' => 1,
                    'bestandsnaam' => 'document.pdf',
                    'inhoud' => 'x',
                    'beschrijving' => '',
                    'informatieobjecttype' => SHARED.'/catalogi/api/v1/informatieobjecttypen/1',
                    'formaat' => 'application/pdf',
                    'locked' => false,
                    'creatiedatum' => now()->toDateString(),
                    'status' => 'definitief',
                ], 200);
        },
    ]);

    dispatch(new DocumentNotificationReceived(
        tenantNotification('documenten', 'enkelvoudiginformatieobject', $documentUrl),
        true,
    ));

    // Tried in a deterministic order and read exactly once more after the refusal.
    expect(clientIdsUsedFor($documentUrl))->toBe(['client-a', 'client-b'])
        ->and($a->id)->toBeLessThan($b->id);
});

test('a document notification no connection may read fails the job with the attempts in the message', function () {
    [, $b] = twoTenantsOnOneInstance();
    $documentUrl = SHARED.'/documenten/api/v1/enkelvoudiginformatieobjecten/1';

    fakeSharedDocument($b, [
        $documentUrl => Http::response(['detail' => 'not allowed'], 403),
    ]);

    $caught = null;

    try {
        dispatch(new DocumentNotificationReceived(
            tenantNotification('documenten', 'enkelvoudiginformatieobject', $documentUrl),
            true,
        ));
    } catch (UnresolvedNotificationConnectionException $e) {
        $caught = $e;
    }

    // The failure names the candidates and what they answered, instead of the
    // misleading "host is not on the allowlist" it used to end on.
    expect($caught)->not->toBeNull()
        ->and($caught->attempts)->toHaveCount(2)
        ->and($caught->getMessage())->toContain('403')
        ->and($caught->getMessage())->not->toContain('allowlist');
});

test('a besluit notification on a shared instance refreshes the zaak instead of being dropped', function () {
    [, $b] = twoTenantsOnOneInstance();

    $besluitUrl = SHARED.'/besluiten/api/v1/besluiten/1';
    $zaakUrl = SHARED.'/zaken/api/v1/zaken/1';

    $zaaktype = Zaaktype::factory()->for($b)->create(['connection' => "gemeente_{$b->id}"]);
    $zaak = Zaak::factory()->for($zaaktype)->create(['zgw_zaak_url' => $zaakUrl]);

    Http::fake([
        $besluitUrl => Http::response(['url' => $besluitUrl, 'zaak' => $zaakUrl], 200),
    ]);

    Cache::put("zaak.{$zaak->id}.besluiten", collect(['stale']));

    dispatch(new BesluitNotificationReceived(tenantNotification(
        'besluiten',
        'besluit',
        $besluitUrl,
        ['verantwoordelijkeOrganisatie' => RSIN_B],
    )));

    expect(Cache::has("zaak.{$zaak->id}.besluiten"))->toBeFalse()
        ->and(clientIdsUsedFor($besluitUrl))->toBe(['client-b']);
});

test('a besluit notification that cannot be attributed fails the job instead of being swallowed', function () {
    twoTenantsOnOneInstance();

    $besluitUrl = SHARED.'/besluiten/api/v1/besluiten/1';

    Http::fake([
        $besluitUrl => Http::response(['detail' => 'not allowed'], 403),
    ]);

    expect(fn () => dispatch(new BesluitNotificationReceived(
        tenantNotification('besluiten', 'besluit', $besluitUrl),
    )))->toThrow(UnresolvedNotificationConnectionException::class);
});

test('a besluit that is gone on every connection is logged and does not fail the job', function () {
    Log::spy();
    twoTenantsOnOneInstance();

    $besluitUrl = SHARED.'/besluiten/api/v1/besluiten/gone';

    Http::fake([
        $besluitUrl => Http::response(['detail' => 'Not found'], 404),
    ]);

    // Nothing tells the candidates apart (a destroy, or a payload from before
    // the kenmerken were carried through), and the besluit is gone everywhere.
    dispatch(new BesluitNotificationReceived(
        tenantNotification('besluiten', 'besluit', $besluitUrl, [], 'destroy'),
    ));

    expect(clientIdsUsedFor($besluitUrl))->toBe(['client-a', 'client-b']);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (...$args) => isset($args[0]) && is_string($args[0]) && str_contains($args[0], 'no longer exists on any connection'))
        ->once();
});

test('a besluit that the owning connection cannot read is still only logged', function () {
    [, $b] = twoTenantsOnOneInstance();

    $besluitUrl = SHARED.'/besluiten/api/v1/besluiten/1';

    Http::fake([
        $besluitUrl => Http::response(['detail' => 'Not found'], 404),
    ]);

    dispatch(new BesluitNotificationReceived(tenantNotification(
        'besluiten',
        'besluit',
        $besluitUrl,
        ['verantwoordelijkeOrganisatie' => RSIN_B],
    )));

    expect(clientIdsUsedFor($besluitUrl))->toBe(['client-b'])
        ->and($b->id)->toBeGreaterThan(0);
});

test('a zaaktype notification on a shared instance is read with the connection of the local zaaktype row', function () {
    [, $b] = twoTenantsOnOneInstance();

    $versionUrl = SHARED.'/catalogi/api/v1/zaaktypen/1';

    Zaaktype::factory()->for($b)->create([
        'connection' => "gemeente_{$b->id}",
        'zgw_zaaktype_url' => $versionUrl,
        'identificatie' => 'OWN-1',
    ]);

    Http::fake([
        $versionUrl => Http::response(['url' => $versionUrl, 'identificatie' => 'OWN-1'], 200),
    ]);

    // No koppeling for OWN-1, so the refresh stops right after the read; the
    // point of the test is which connection did the reading.
    (new ZaaktypeNotificationReceived(tenantNotification('zaaktypen', 'zaaktype', $versionUrl, [], 'partial_update')))
        ->handle(app(ZaaktypeRefresher::class));

    expect(clientIdsUsedFor($versionUrl))->toBe(['client-b']);
});

test('a destroyed zaaktype version that no connection still has is ignored, not failed', function () {
    twoTenantsOnOneInstance();

    $versionUrl = SHARED.'/catalogi/api/v1/zaaktypen/gone';

    Http::fake([
        $versionUrl => Http::response(['detail' => 'Not found'], 404),
    ]);

    (new ZaaktypeNotificationReceived(tenantNotification('zaaktypen', 'zaaktype', $versionUrl, [], 'destroy')))
        ->handle(app(ZaaktypeRefresher::class));

    expect(clientIdsUsedFor($versionUrl))->toBe(['client-a', 'client-b']);
});

test('a single connection owning a host still needs no kenmerk', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->active()->create(
        tenantConnection(RSIN_A, 'client-solo'),
    );

    $documentUrl = fakeSharedDocument($municipality);

    dispatch(new DocumentNotificationReceived(
        tenantNotification('documenten', 'enkelvoudiginformatieobject', $documentUrl),
        true,
    ));

    expect(clientIdsUsedFor($documentUrl))->toBe(['client-solo']);
});
