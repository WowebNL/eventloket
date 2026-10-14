<?php

declare(strict_types=1);

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\ZgwConnectionResolver;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Cache::flush();
    $this->resolver = app(ZgwConnectionResolver::class);
});

/** The shared instance two municipalities are both configured against. */
const SHARED_BASE = 'https://shared.example.com';

/**
 * A connection on the shared instance, carrying its own RSIN.
 *
 * @return array<string, mixed>
 */
function sharedInstanceConnection(string $rsin): array
{
    return [
        'zaken_url' => SHARED_BASE.'/zaken/api/v1/',
        'catalogi_url' => SHARED_BASE.'/catalogi/api/v1/',
        'documenten_url' => SHARED_BASE.'/documenten/api/v1/',
        'besluiten_url' => SHARED_BASE.'/besluiten/api/v1/',
        'autorisaties_url' => SHARED_BASE.'/autorisaties/api/v1/',
        'notificaties_url' => SHARED_BASE.'/notificaties/api/v1/',
        'allowed_hosts' => [],
        'bronorganisatie_rsin' => $rsin,
    ];
}

/**
 * @param  array<string, string>  $kenmerken
 */
function zgwNotification(string $kanaal, string $hoofdObject, array $kenmerken = [], string $resource = 'zaak'): OpenNotification
{
    return new OpenNotification(
        actie: 'create',
        kanaal: $kanaal,
        resource: $resource,
        hoofdObject: $hoofdObject,
        resourceUrl: $hoofdObject,
        aanmaakdatum: now()->toIso8601String(),
        kenmerken: $kenmerken,
    );
}

/**
 * Two activated connections against one instance, each with its own RSIN.
 *
 * @return array{0: Municipality, 1: Municipality}
 */
function twoConnectionsOnOneInstance(string $rsinA = '111111110', string $rsinB = '222222220'): array
{
    $a = Municipality::factory()->create();
    $b = Municipality::factory()->create();

    MunicipalityZgwConnection::factory()->for($a)->active()->create(sharedInstanceConnection($rsinA));
    MunicipalityZgwConnection::factory()->for($b)->active()->create(sharedInstanceConnection($rsinB));

    return [$a, $b];
}

it('resolves a document notification to the connection whose rsin matches the kenmerk', function () {
    [$a, $b] = twoConnectionsOnOneInstance();

    $resolution = $this->resolver->forNotification(zgwNotification(
        'documenten',
        SHARED_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        ['bronorganisatie' => '222222220'],
        'enkelvoudiginformatieobject',
    ));

    expect($resolution->connection)->toBe("gemeente_{$b->id}")
        ->and($resolution->foreign)->toBeFalse()
        // Without the kenmerk the url alone cannot tell the two apart.
        ->and($this->resolver->forUrl(SHARED_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/1'))->toBe('main')
        ->and($a->id)->not->toBe($b->id);
});

it('resolves a besluit notification on the verantwoordelijkeOrganisatie kenmerk', function () {
    [$a] = twoConnectionsOnOneInstance();

    $resolution = $this->resolver->forNotification(zgwNotification(
        'besluiten',
        SHARED_BASE.'/besluiten/api/v1/besluiten/1',
        ['verantwoordelijkeOrganisatie' => '111111110'],
        'besluit',
    ));

    expect($resolution->connection)->toBe("gemeente_{$a->id}");
});

it('treats a notification whose organisation matches no connection as another tenant', function () {
    twoConnectionsOnOneInstance();

    $resolution = $this->resolver->forNotification(zgwNotification(
        'documenten',
        SHARED_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        ['bronorganisatie' => '999999990'],
        'enkelvoudiginformatieobject',
    ));

    expect($resolution->foreign)->toBeTrue()
        ->and($resolution->connection)->toBeNull()
        ->and($resolution->organisatie)->toBe('999999990');
});

it('leaves a notification without an organisation kenmerk undecided, listing every candidate', function () {
    [$a, $b] = twoConnectionsOnOneInstance();

    $resolution = $this->resolver->forNotification(zgwNotification(
        'documenten',
        SHARED_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        [],
        'enkelvoudiginformatieobject',
    ));

    expect($resolution->connection)->toBeNull()
        ->and($resolution->foreign)->toBeFalse()
        ->and($resolution->candidates)->toBe(["gemeente_{$a->id}", "gemeente_{$b->id}"]);
});

it('narrows to the candidates sharing an rsin when the kenmerk does not discriminate', function () {
    [$a, $b] = twoConnectionsOnOneInstance('333333330', '333333330');

    $resolution = $this->resolver->forNotification(zgwNotification(
        'documenten',
        SHARED_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        ['bronorganisatie' => '333333330'],
        'enkelvoudiginformatieobject',
    ));

    expect($resolution->connection)->toBeNull()
        ->and($resolution->foreign)->toBeFalse()
        ->and($resolution->candidates)->toBe(["gemeente_{$a->id}", "gemeente_{$b->id}"]);
});

it('prefers the local zaak over the organisation kenmerk', function () {
    [$a, $b] = twoConnectionsOnOneInstance();

    $zaakUrl = SHARED_BASE.'/zaken/api/v1/zaken/1';
    $zaaktype = Zaaktype::factory()->for($a)->create(['connection' => "gemeente_{$a->id}"]);
    Zaak::factory()->for($zaaktype)->create(['zgw_zaak_url' => $zaakUrl]);

    $resolution = $this->resolver->forNotification(zgwNotification('zaken', $zaakUrl, [
        // A kenmerk pointing elsewhere does not override what we know locally.
        'bronorganisatie' => '222222220',
    ], 'status'));

    expect($resolution->connection)->toBe("gemeente_{$a->id}")
        ->and($b->id)->not->toBe($a->id);
});

it('resolves a zaaktype notification through the local zaaktype row', function () {
    [$a] = twoConnectionsOnOneInstance();

    $versionUrl = SHARED_BASE.'/catalogi/api/v1/zaaktypen/1';
    Zaaktype::factory()->for($a)->create([
        'connection' => "gemeente_{$a->id}",
        'zgw_zaaktype_url' => $versionUrl,
    ]);

    $resolution = $this->resolver->forNotification(zgwNotification('zaaktypen', $versionUrl, [], 'zaaktype'));

    expect($resolution->connection)->toBe("gemeente_{$a->id}");
});

it('leaves an unknown zaaktype version on a shared instance to the candidates', function () {
    [$a, $b] = twoConnectionsOnOneInstance();

    $resolution = $this->resolver->forNotification(zgwNotification(
        'zaaktypen',
        SHARED_BASE.'/catalogi/api/v1/zaaktypen/unknown',
        // The zaaktypen channel carries the catalogus, never an organisation.
        ['catalogus' => SHARED_BASE.'/catalogi/api/v1/catalogussen/1'],
        'zaaktype',
    ));

    expect($resolution->connection)->toBeNull()
        ->and($resolution->candidates)->toBe(["gemeente_{$a->id}", "gemeente_{$b->id}"]);
});

it('decides on the single connection that owns a host without needing a kenmerk', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->active()->create(
        sharedInstanceConnection('444444440'),
    );

    $resolution = $this->resolver->forNotification(zgwNotification(
        'documenten',
        SHARED_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        [],
        'enkelvoudiginformatieobject',
    ));

    expect($resolution->connection)->toBe("gemeente_{$municipality->id}");
});

it('keeps falling back to main for a host no connection claims', function () {
    $resolution = $this->resolver->forNotification(zgwNotification(
        'documenten',
        'https://unknown.example.com/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        ['bronorganisatie' => '111111110'],
        'enkelvoudiginformatieobject',
    ));

    expect($resolution->connection)->toBe('main');
});

it('ignores an inactive connection when listing candidates', function () {
    [$a] = twoConnectionsOnOneInstance();

    $inactive = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($inactive)->create(sharedInstanceConnection('555555550'));

    $resolution = $this->resolver->forNotification(zgwNotification(
        'documenten',
        SHARED_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        ['bronorganisatie' => '555555550'],
        'enkelvoudiginformatieobject',
    ));

    // The RSIN belongs to a connection that is not live, so it is not a
    // candidate and the notification is not ours to process.
    expect($resolution->foreign)->toBeTrue()
        ->and($resolution->candidates)->not->toContain("gemeente_{$inactive->id}")
        ->and($resolution->candidates)->toContain("gemeente_{$a->id}");
});

it('logs a warning naming the connections that share a host', function () {
    Log::spy();

    twoConnectionsOnOneInstance();

    $this->resolver->forUrl(SHARED_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/1');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'more than one connection')
            && ($context['host'] ?? null) === 'shared.example.com'
            && count($context['owners'] ?? []) === 2)
        ->once();
});
