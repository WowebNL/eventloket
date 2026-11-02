<?php

declare(strict_types=1);

use App\Jobs\Notificaties\RenewZgwAbonnementen;
use App\Models\MunicipalityZgwConnection;
use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementCheckStatus;
use App\Services\Notificaties\AbonnementHealthCheck;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\NotificatiesApi;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\ZgwManager;

/*
 * Every connection subscribes with the same callback URL, and the webhook token
 * on an abonnement identifies the connection a notification is attributed to.
 * These tests cover connections whose Notificaties API is the same instance.
 *
 * Statuses and outcomes are compared by name so that each test fails on its own
 * assertion, not on a missing case, when run against code without them.
 */

const MAIN_NC = 'https://nc-main.example.com/api/v1/';
const OWN_NC = 'https://nc-own.example.com/api/v1/';
const NEW_NC = 'https://nc-new.example.com/api/v1/';

/**
 * A minimal, stateful Notificaties API: it keeps the abonnementen it was given,
 * lists them to any caller and lets any caller patch or delete them, and (like
 * Open Notificaties) never returns the write-only auth value.
 *
 * A tenant-scoped instance instead models a OneGround Notificaties API with one
 * tenant per ZGW client id: a caller only lists, reads, patches and deletes the
 * abonnementen its own client created.
 */
final class FakeNotificatiesInstance
{
    /** @var array<string, array<string, mixed>> */
    public array $abonnementen = [];

    private int $sequence = 0;

    public function __construct(public readonly string $base, private readonly bool $tenantScoped = false) {}

    /** Put an abonnement on the instance as if someone registered it earlier. */
    public function seed(string $url, string $callbackUrl, string $auth, string $tenant = ''): void
    {
        $this->abonnementen[$url] = ['url' => $url, 'callbackUrl' => $callbackUrl, 'auth' => $auth, 'kanalen' => self::kanalen(), 'tenant' => $tenant];
    }

    public function handle(Request $request): mixed
    {
        $url = $request->url();
        $tenant = $this->tenantScoped ? self::zgwClientId($request) : '';

        if ($url === $this->base.'abonnement') {
            if ($request->method() === 'GET') {
                $visible = $this->visibleTo($tenant);

                return Http::response([
                    'count' => count($visible),
                    'next' => null,
                    'previous' => null,
                    'results' => array_values(array_map(self::public(...), $visible)),
                ], 200);
            }

            $created = $this->base.'abonnement/'.(++$this->sequence);
            $this->abonnementen[$created] = [
                'url' => $created,
                'callbackUrl' => $request['callbackUrl'],
                'auth' => $request['auth'],
                'kanalen' => self::kanalen(),
                'tenant' => $tenant,
            ];

            return Http::response(self::public($this->abonnementen[$created]), 201);
        }

        if (! isset($this->visibleTo($tenant)[$url])) {
            return Http::response(['detail' => 'Niet gevonden.'], 404);
        }

        if ($request->method() === 'DELETE') {
            unset($this->abonnementen[$url]);

            return Http::response(null, 204);
        }

        if ($request->method() === 'PATCH') {
            $this->abonnementen[$url] = array_merge($this->abonnementen[$url], $request->data());
        }

        return Http::response(self::public($this->abonnementen[$url]), 200);
    }

    /**
     * The abonnementen the given ZGW client created on a tenant-scoped instance.
     *
     * @return array<string, array<string, mixed>>
     */
    public function ofTenant(string $tenant): array
    {
        return array_filter($this->abonnementen, fn (array $abonnement): bool => $abonnement['tenant'] === $tenant);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function visibleTo(string $tenant): array
    {
        return $this->tenantScoped ? $this->ofTenant($tenant) : $this->abonnementen;
    }

    /** The client id claim of the ZGW JWT the request authenticates with. */
    private static function zgwClientId(Request $request): string
    {
        $jwt = (string) preg_replace('/^Bearer\s+/', '', $request->header('Authorization')[0] ?? '');
        $claims = json_decode((string) base64_decode(strtr(explode('.', $jwt)[1] ?? '', '-_', '+/'), true), true);

        return is_array($claims) && is_string($claims['client_id'] ?? null) ? $claims['client_id'] : '';
    }

    /** The auth value currently set on the remote abonnement with the given URL. */
    public function authOf(string $url): ?string
    {
        return $this->abonnementen[$url]['auth'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $abonnement
     * @return array<string, mixed>
     */
    private static function public(array $abonnement): array
    {
        unset($abonnement['auth'], $abonnement['tenant']);

        return $abonnement;
    }

    /** @return list<array{naam: string, filters: array<string, mixed>}> */
    private static function kanalen(): array
    {
        return array_map(fn (string $naam): array => ['naam' => $naam, 'filters' => []], AbonnementRegistrar::KANALEN);
    }
}

/**
 * A JWT-shaped webhook token whose claims say which Passport client and token it
 * is, like the tokens Passport issues. Only the claims matter here.
 */
function sharedInstanceJwt(string $tokenId, string $clientId): string
{
    $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

    return $segment(['typ' => 'JWT', 'alg' => 'RS256']).'.'.$segment(['jti' => $tokenId, 'aud' => $clientId]).'.signature';
}

/**
 * The connection a notification presenting this auth value is attributed to:
 * the abonnement row of the client the token was issued to. This is the same
 * lookup the webhook uses to establish the sender.
 */
function attributedConnection(?string $auth): ?string
{
    if (! is_string($auth)) {
        return null;
    }

    $parts = explode('.', (string) preg_replace('/^Bearer\s+/', '', $auth));
    $claims = json_decode((string) base64_decode(strtr($parts[1] ?? '', '-_', '+/'), true), true);
    $clientId = is_array($claims) ? ($claims['aud'] ?? null) : null;

    return is_string($clientId)
        ? ZgwAbonnement::query()->where('client_id', $clientId)->value('connection')
        : null;
}

/**
 * Fake the token endpoint and the given Notificaties instances. Every issued
 * token gets its own id and carries the client it was issued to.
 */
function fakeNotificatiesInstances(FakeNotificatiesInstance ...$instances): void
{
    $issued = 0;
    $fakes = [
        rtrim((string) config('app.url'), '/').'/oauth/token' => function (Request $request) use (&$issued) {
            return Http::response([
                'access_token' => sharedInstanceJwt('tok-'.(++$issued), (string) $request['client_id']),
                'token_type' => 'Bearer',
                'expires_in' => 31536000,
            ], 200);
        },
    ];

    foreach ($instances as $instance) {
        $fakes[$instance->base.'*'] = fn (Request $request) => $instance->handle($request);
    }

    Http::fake($fakes);
}

/**
 * A municipality connection with the given notificaties URL (null inherits the
 * main one), with its runtime config registered the way the resolver does.
 *
 * @param  array<string, mixed>  $attributes
 */
function municipalityConnection(?string $notificatiesUrl, array $attributes = []): string
{
    $connection = MunicipalityZgwConnection::factory()->create(['notificaties_url' => $notificatiesUrl, ...$attributes]);
    $name = 'gemeente_'.$connection->municipality_id;

    config()->set("zgw.connections.{$name}", $connection->buildConfig());

    return $name;
}

function registrar(): AbonnementRegistrar
{
    return app(AbonnementRegistrar::class);
}

beforeEach(function () {
    Cache::flush();
    config()->set('zgw.connections.main.urls.notificaties', MAIN_NC);
});

it('keeps main\'s notifications attributed to main when a municipality on the same instance registers', function (?string $municipalityNotificatiesUrl) {
    $instance = new FakeNotificatiesInstance(MAIN_NC);
    fakeNotificatiesInstances($instance);
    $municipality = municipalityConnection($municipalityNotificatiesUrl);

    registrar()->register('main');
    $outcome = registrar()->register($municipality);

    // One abonnement on the instance: a second one would deliver every
    // notification twice.
    expect($instance->abonnementen)->toHaveCount(1);

    $remoteUrl = array_key_first($instance->abonnementen);

    // The token on that abonnement is still main's, so main's notifications are
    // attributed to main and not to the municipality that registered second.
    expect(attributedConnection($instance->authOf($remoteUrl)))->toBe('main')
        ->and(ZgwAbonnement::where('connection', 'main')->value('abonnement_url'))->toBe($remoteUrl)
        ->and(ZgwAbonnement::where('connection', $municipality)->exists())->toBeFalse()
        ->and($outcome->name)->toBe('RefusedSharedInstance');
})->with([
    'notificaties url inherited from main' => [null],
    'notificaties url set to main\'s instance' => [MAIN_NC],
]);

it('attributes each connection\'s notifications to itself when each has its own notificaties instance', function () {
    $mainInstance = new FakeNotificatiesInstance(MAIN_NC);
    $ownInstance = new FakeNotificatiesInstance(OWN_NC);
    fakeNotificatiesInstances($mainInstance, $ownInstance);
    $municipality = municipalityConnection(OWN_NC);

    registrar()->register('main');
    registrar()->register($municipality);
    // A refresh of either must not move the other's attribution.
    registrar()->register('main');
    registrar()->register($municipality);

    expect($mainInstance->abonnementen)->toHaveCount(1)
        ->and($ownInstance->abonnementen)->toHaveCount(1)
        ->and(attributedConnection($mainInstance->authOf(array_key_first($mainInstance->abonnementen))))->toBe('main')
        ->and(attributedConnection($ownInstance->authOf(array_key_first($ownInstance->abonnementen))))->toBe($municipality);
});

it('leaves the abonnement with the connection that registered first when two municipalities share an instance', function () {
    $instance = new FakeNotificatiesInstance(OWN_NC);
    fakeNotificatiesInstances($instance);
    $first = municipalityConnection(OWN_NC);
    $second = municipalityConnection(OWN_NC);

    expect(registrar()->register($first)->name)->toBe('Created')
        ->and(registrar()->register($second)->name)->toBe('RefusedSharedInstance')
        ->and(registrar()->register($first)->name)->toBe('Updated');

    expect($instance->abonnementen)->toHaveCount(1)
        ->and(attributedConnection($instance->authOf(array_key_first($instance->abonnementen))))->toBe($first);
});

it('refuses to register when another connection holds our abonnement on the instance under a different url', function () {
    $instance = new FakeNotificatiesInstance(OWN_NC);
    fakeNotificatiesInstances($instance);
    $municipality = municipalityConnection(OWN_NC);

    // Another connection reaches this same instance through an alias and has
    // already subscribed there.
    $instance->seed(OWN_NC.'abonnement/alias', registrar()->callbackUrl(), 'Bearer '.sharedInstanceJwt('tok-alias', 'client-alias'));
    ZgwAbonnement::factory()->create([
        'connection' => 'main',
        'notificaties_base_url' => 'https://nc-alias.example.com/api/v1/',
        'abonnement_url' => OWN_NC.'abonnement/alias',
        'token_id' => 'tok-alias',
        'client_id' => 'client-alias',
    ]);

    expect(registrar()->register($municipality)->name)->toBe('RefusedSharedInstance');

    expect($instance->authOf(OWN_NC.'abonnement/alias'))->toBe('Bearer '.sharedInstanceJwt('tok-alias', 'client-alias'))
        ->and($instance->abonnementen)->toHaveCount(1);
    Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['POST', 'PATCH'], true)
        && str_starts_with($request->url(), OWN_NC));
});

describe('findRemote', function () {
    beforeEach(function () {
        $this->instance = new FakeNotificatiesInstance(OWN_NC);
        fakeNotificatiesInstances($this->instance);
        $this->municipality = municipalityConnection(OWN_NC);
        $this->callback = registrar()->callbackUrl();
        $this->api = new NotificatiesApi($this->municipality);
    });

    it('returns the connection\'s own stored abonnement, not the first one on the callback', function () {
        $this->instance->seed(OWN_NC.'abonnement/other', $this->callback, 'Bearer other');
        $this->instance->seed(OWN_NC.'abonnement/own', $this->callback, 'Bearer own');
        ZgwAbonnement::factory()->create(['connection' => 'main', 'abonnement_url' => OWN_NC.'abonnement/other']);
        ZgwAbonnement::factory()->create(['connection' => $this->municipality, 'notificaties_base_url' => OWN_NC, 'abonnement_url' => OWN_NC.'abonnement/own']);

        expect(registrar()->findRemote($this->api, $this->callback, $this->municipality)['url'] ?? null)
            ->toBe(OWN_NC.'abonnement/own');
    });

    it('never returns an abonnement another connection has stored', function () {
        $this->instance->seed(OWN_NC.'abonnement/other', $this->callback, 'Bearer other');
        ZgwAbonnement::factory()->create(['connection' => 'main', 'abonnement_url' => OWN_NC.'abonnement/other']);

        expect(registrar()->findRemote($this->api, $this->callback, $this->municipality))->toBeNull();
    });

    it('falls back to an unclaimed abonnement on the callback when its stored one is gone', function () {
        $this->instance->seed(OWN_NC.'abonnement/other', $this->callback, 'Bearer other');
        $this->instance->seed(OWN_NC.'abonnement/unclaimed', $this->callback, 'Bearer unclaimed');
        ZgwAbonnement::factory()->create(['connection' => 'main', 'abonnement_url' => OWN_NC.'abonnement/other']);
        ZgwAbonnement::factory()->create(['connection' => $this->municipality, 'notificaties_base_url' => OWN_NC, 'abonnement_url' => OWN_NC.'abonnement/gone']);

        expect(registrar()->findRemote($this->api, $this->callback, $this->municipality)['url'] ?? null)
            ->toBe(OWN_NC.'abonnement/unclaimed');
    });
});

/**
 * Point a municipality connection at another notificaties URL, the way an admin
 * edit does, and drop the cached ZGW connections so the new URL is used.
 */
function moveNotificatiesUrl(MunicipalityZgwConnection $model, string $connectionName, string $url): void
{
    $model->update(['notificaties_url' => $url]);
    config()->set("zgw.connections.{$connectionName}", $model->fresh()->buildConfig());
    app()->forgetInstance(ZgwManager::class);
    Zgw::clearResolvedInstance(ZgwManager::class);
}

describe('stored abonnement on another instance', function () {
    it('registers on the new instance after a municipality moves its notificaties url', function () {
        $old = new FakeNotificatiesInstance(OWN_NC);
        $new = new FakeNotificatiesInstance(NEW_NC);
        fakeNotificatiesInstances($old, $new);
        $municipality = municipalityConnection(OWN_NC);
        expect(registrar()->register($municipality)->name)->toBe('Created');

        moveNotificatiesUrl(MunicipalityZgwConnection::query()->firstOrFail(), $municipality, NEW_NC);
        $sentBefore = count(Http::recorded());

        $outcome = registrar()->register($municipality);

        expect($outcome->name)->toBe('Created')
            ->and($new->abonnementen)->toHaveCount(1)
            ->and(attributedConnection($new->authOf(array_key_first($new->abonnementen))))->toBe($municipality)
            ->and(ZgwAbonnement::where('connection', $municipality)->value('abonnement_url'))->toBe(array_key_first($new->abonnementen));

        // Nothing of this registration went to the previous instance.
        $toOld = collect(Http::recorded())
            ->slice($sentBefore)
            ->filter(fn (array $pair): bool => str_starts_with($pair[0]->url(), OWN_NC));
        expect($toOld)->toBeEmpty();
    });

    it('does not take main\'s abonnement over when a municipality that inherited the url fills in its own', function () {
        $mainInstance = new FakeNotificatiesInstance(MAIN_NC);
        $ownInstance = new FakeNotificatiesInstance(OWN_NC);
        fakeNotificatiesInstances($mainInstance, $ownInstance);

        // The state an earlier registration could leave behind: main and the
        // municipality (which inherited main's notificaties url) both store
        // main's abonnement, and main's host is reachable for the municipality.
        registrar()->register('main');
        $mainAbonnement = (string) ZgwAbonnement::where('connection', 'main')->value('abonnement_url');
        $model = MunicipalityZgwConnection::factory()->create([
            'notificaties_url' => null,
            'allowed_hosts' => ['https://nc-main.example.com'],
        ]);
        $municipality = 'gemeente_'.$model->municipality_id;
        ZgwAbonnement::factory()->create([
            'connection' => $municipality,
            'abonnement_url' => $mainAbonnement,
            'notificaties_base_url' => MAIN_NC,
        ]);

        moveNotificatiesUrl($model, $municipality, OWN_NC);

        expect(registrar()->register($municipality)->name)->toBe('Created')
            ->and(attributedConnection($mainInstance->authOf($mainAbonnement)))->toBe('main')
            ->and($ownInstance->abonnementen)->toHaveCount(1)
            ->and(attributedConnection($ownInstance->authOf(array_key_first($ownInstance->abonnementen))))->toBe($municipality);
    });
});

it('lets main register on its own instance when a municipality row holds the abonnement there', function () {
    $mainInstance = new FakeNotificatiesInstance(MAIN_NC);
    fakeNotificatiesInstances($mainInstance);
    $mainInstance->seed(MAIN_NC.'abonnement/1', registrar()->callbackUrl(), 'Bearer '.sharedInstanceJwt('tok-m', 'client-m'));
    $municipality = municipalityConnection(null);
    ZgwAbonnement::factory()->create([
        'connection' => $municipality,
        'abonnement_url' => MAIN_NC.'abonnement/1',
        'notificaties_base_url' => MAIN_NC,
        'client_id' => 'client-m',
        'token_id' => 'tok-m',
    ]);

    expect(registrar()->register('main')->name)->toBe('Updated')
        ->and($mainInstance->abonnementen)->toHaveCount(1)
        ->and(attributedConnection($mainInstance->authOf(MAIN_NC.'abonnement/1')))->toBe('main');

    // The municipality row still claims the same abonnement, which the health
    // check reports instead of offering to register again.
    expect(app(AbonnementHealthCheck::class)->run('main')->status->name)->toBe('SharedAbonnement');
});

describe('health check', function () {
    it('alarms when two connections store the same abonnement', function () {
        $instance = new FakeNotificatiesInstance(MAIN_NC);
        fakeNotificatiesInstances($instance);
        $municipality = municipalityConnection(OWN_NC);

        $instance->seed(MAIN_NC.'abonnement/1', registrar()->callbackUrl(), 'Bearer x');
        ZgwAbonnement::factory()->create(['connection' => 'main', 'notificaties_base_url' => MAIN_NC, 'abonnement_url' => MAIN_NC.'abonnement/1']);
        ZgwAbonnement::factory()->create(['connection' => $municipality, 'abonnement_url' => MAIN_NC.'abonnement/1']);

        $result = app(AbonnementHealthCheck::class)->run('main');

        expect($result->status->name)->toBe('SharedAbonnement')
            ->and($result->status->isDanger())->toBeTrue()
            ->and($result->otherConnection)->toBe($municipality);
    });

    it('alarms when the remote abonnement presents another connection\'s token', function () {
        Http::fake([MAIN_NC.'abonnement/1' => Http::response([
            'url' => MAIN_NC.'abonnement/1',
            'auth' => 'Bearer '.sharedInstanceJwt('tok-someone-else', 'client-someone-else'),
            'kanalen' => array_map(fn (string $naam): array => ['naam' => $naam, 'filters' => []], AbonnementRegistrar::KANALEN),
        ], 200)]);
        ZgwAbonnement::factory()->create(['connection' => 'main', 'notificaties_base_url' => MAIN_NC, 'abonnement_url' => MAIN_NC.'abonnement/1', 'token_id' => 'tok-own']);

        $result = app(AbonnementHealthCheck::class)->run('main');

        expect($result->status->name)->toBe('AuthMismatch')
            ->and($result->status->isDanger())->toBeTrue();
    });

    it('stays healthy when the remote abonnement presents the connection\'s own token', function () {
        Http::fake([MAIN_NC.'abonnement/1' => Http::response([
            'url' => MAIN_NC.'abonnement/1',
            'auth' => 'Bearer '.sharedInstanceJwt('tok-own', 'client-own'),
            'kanalen' => array_map(fn (string $naam): array => ['naam' => $naam, 'filters' => []], AbonnementRegistrar::KANALEN),
        ], 200)]);
        ZgwAbonnement::factory()->create(['connection' => 'main', 'notificaties_base_url' => MAIN_NC, 'abonnement_url' => MAIN_NC.'abonnement/1', 'token_id' => 'tok-own']);

        expect(app(AbonnementHealthCheck::class)->run('main')->status)->toBe(AbonnementCheckStatus::Healthy);
    });

    it('reports a municipality connection on main\'s instance instead of asking to register it', function (?string $notificatiesUrl, string $expected) {
        Http::fake();
        $municipality = municipalityConnection($notificatiesUrl);

        $result = app(AbonnementHealthCheck::class)->run($municipality);

        expect($result->status->name)->toBe($expected)
            ->and($result->status->isDanger())->toBeTrue()
            ->and($result->otherConnection)->toBe('main');
        Http::assertNothingSent();
    })->with([
        'inherited' => [null, 'NotificatiesUrlInherited'],
        'explicitly the same' => [MAIN_NC, 'NotificatiesInstanceInUse'],
    ]);
});

it('renews a shared abonnement only for the connection that owns it', function () {
    $instance = new FakeNotificatiesInstance(MAIN_NC);
    fakeNotificatiesInstances($instance);
    registrar()->register('main');
    $abonnement = (string) ZgwAbonnement::where('connection', 'main')->value('abonnement_url');

    // A duplicate row an earlier registration could leave behind: a
    // municipality that inherited main's notificaties url stores the same
    // abonnement. Both are inside the renewal window.
    $municipality = municipalityConnection(null);
    ZgwAbonnement::factory()->create([
        'connection' => $municipality,
        'abonnement_url' => $abonnement,
        'notificaties_base_url' => MAIN_NC,
        'expires_at' => now()->addDay(),
    ]);
    ZgwAbonnement::where('connection', 'main')->update(['expires_at' => now()->addDay()]);
    $municipalityTokenBefore = ZgwAbonnement::where('connection', $municipality)->value('token_id');

    (new RenewZgwAbonnementen)->handle(app(WebhookTokenIssuer::class), registrar());

    expect(attributedConnection($instance->authOf($abonnement)))->toBe('main')
        ->and(ZgwAbonnement::where('connection', $municipality)->value('token_id'))->toBe($municipalityTokenBefore)
        ->and(ZgwAbonnement::where('connection', 'main')->value('expires_at')->isAfter(now()->addMonth()))->toBeTrue();
});

describe('instance spelled differently by the api', function () {
    // The same instance as MAIN_NC, configured with an explicit default port.
    // The Notificaties API returns abonnement URLs in its own spelling.
    beforeEach(function () {
        config()->set('zgw.connections.main.urls.notificaties', 'https://nc-main.example.com:443/api/v1/');
    });

    it('keeps renewing the abonnement', function () {
        $instance = new FakeNotificatiesInstance(MAIN_NC);
        fakeNotificatiesInstances($instance);
        $issued = app(WebhookTokenIssuer::class)->issue();
        $instance->seed(MAIN_NC.'abonnement/1', registrar()->callbackUrl(), 'Bearer '.$issued->token);
        ZgwAbonnement::factory()->create([
            'connection' => 'main',
            'notificaties_base_url' => 'https://nc-main.example.com:443/api/v1/',
            'abonnement_url' => MAIN_NC.'abonnement/1',
            'token_id' => $issued->tokenId,
            'client_id' => $issued->clientId,
            'expires_at' => now()->addDay(),
        ]);

        (new RenewZgwAbonnementen)->handle(app(WebhookTokenIssuer::class), registrar());

        expect(ZgwAbonnement::where('connection', 'main')->value('token_id'))->not->toBe($issued->tokenId)
            ->and(attributedConnection($instance->authOf(MAIN_NC.'abonnement/1')))->toBe('main');
    });

    it('reports the abonnement as healthy', function () {
        $instance = new FakeNotificatiesInstance(MAIN_NC);
        fakeNotificatiesInstances($instance);
        $instance->seed(MAIN_NC.'abonnement/1', registrar()->callbackUrl(), 'Bearer x');
        ZgwAbonnement::factory()->create([
            'connection' => 'main',
            'notificaties_base_url' => 'https://nc-main.example.com:443/api/v1/',
            'abonnement_url' => MAIN_NC.'abonnement/1',
            'expires_at' => now()->addMonths(6),
        ]);

        expect(app(AbonnementHealthCheck::class)->run('main')->status->name)->toBe('Healthy');
    });
});

/**
 * Seed an abonnement for each of two OneGround connections on the same
 * notificaties URL, each in the tenant of its own client id, the way their
 * registrations leave it.
 *
 * @return array{0: string, 1: string}
 */
function seedOneGroundTenants(FakeNotificatiesInstance $instance): array
{
    $names = [];

    foreach (['tenant-a-client', 'tenant-b-client'] as $index => $clientId) {
        $name = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => $clientId]);
        $issued = app(WebhookTokenIssuer::class)->issue();
        $url = OWN_NC.'abonnement/seeded-'.$index;
        $instance->seed($url, registrar()->callbackUrl(), 'Bearer '.$issued->token, $clientId);
        ZgwAbonnement::factory()->create([
            'connection' => $name,
            'notificaties_base_url' => OWN_NC,
            'abonnement_url' => $url,
            'token_id' => $issued->tokenId,
            'client_id' => $issued->clientId,
            'expires_at' => now()->addDay(),
        ]);
        $names[] = $name;
    }

    return [$names[0], $names[1]];
}

/*
 * A OneGround Notificaties API binds each ZGW client id to its tenant, so
 * connections with their own client id on such an instance each get their own
 * abonnement, even when their notificaties URL is the same. When the instance
 * does list another client's abonnement, registration falls back to a single
 * abonnement.
 */
describe('oneground connections on a shared notificaties url', function () {
    beforeEach(function () {
        $this->instance = new FakeNotificatiesInstance(OWN_NC, tenantScoped: true);
        fakeNotificatiesInstances($this->instance);
    });

    it('gives each connection with its own client id its own abonnement', function () {
        $first = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => 'tenant-a-client']);
        $second = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => 'tenant-b-client']);

        expect(registrar()->register($first)->name)->toBe('Created')
            ->and(registrar()->register($second)->name)->toBe('Created')
            // A refresh of either keeps its own abonnement.
            ->and(registrar()->register($first)->name)->toBe('Updated')
            ->and(registrar()->register($second)->name)->toBe('Updated');

        $ofFirst = $this->instance->ofTenant('tenant-a-client');
        $ofSecond = $this->instance->ofTenant('tenant-b-client');

        expect($ofFirst)->toHaveCount(1)
            ->and($ofSecond)->toHaveCount(1)
            ->and(attributedConnection($this->instance->authOf((string) array_key_first($ofFirst))))->toBe($first)
            ->and(attributedConnection($this->instance->authOf((string) array_key_first($ofSecond))))->toBe($second)
            ->and(ZgwAbonnement::where('connection', $first)->value('abonnement_url'))->toBe(array_key_first($ofFirst))
            ->and(ZgwAbonnement::where('connection', $second)->value('abonnement_url'))->toBe(array_key_first($ofSecond));
    });

    it('renews each connection\'s own abonnement', function () {
        [$first, $second] = seedOneGroundTenants($this->instance);
        $tokensBefore = ZgwAbonnement::query()->pluck('token_id', 'connection')->all();

        (new RenewZgwAbonnementen)->handle(app(WebhookTokenIssuer::class), registrar());

        expect(ZgwAbonnement::where('connection', $first)->value('token_id'))->not->toBe($tokensBefore[$first])
            ->and(ZgwAbonnement::where('connection', $second)->value('token_id'))->not->toBe($tokensBefore[$second])
            ->and(attributedConnection($this->instance->authOf(OWN_NC.'abonnement/seeded-0')))->toBe($first)
            ->and(attributedConnection($this->instance->authOf(OWN_NC.'abonnement/seeded-1')))->toBe($second);
    });

    it('reports each connection\'s own abonnement as healthy', function () {
        [$first, $second] = seedOneGroundTenants($this->instance);
        ZgwAbonnement::query()->update(['expires_at' => now()->addMonths(6)]);

        expect(app(AbonnementHealthCheck::class)->run($first)->status->name)->toBe('Healthy')
            ->and(app(AbonnementHealthCheck::class)->run($second)->status->name)->toBe('Healthy');
    });

    it('leaves the other connection\'s abonnement alone when one is removed', function () {
        $first = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => 'tenant-a-client']);
        $second = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => 'tenant-b-client']);
        registrar()->register($first);
        registrar()->register($second);
        $firstUrl = (string) ZgwAbonnement::where('connection', $first)->value('abonnement_url');
        $secondUrl = (string) ZgwAbonnement::where('connection', $second)->value('abonnement_url');

        $this->artisan('app:list-zgw-abonnementen', ['--connection' => $first, '--delete' => $firstUrl, '--force' => true])
            ->assertSuccessful();

        expect($this->instance->ofTenant('tenant-a-client'))->toBeEmpty()
            ->and(ZgwAbonnement::where('connection', $first)->exists())->toBeFalse()
            ->and($secondUrl)->not->toBe('')
            ->and($this->instance->ofTenant('tenant-b-client'))->toHaveKey($secondUrl)
            ->and(ZgwAbonnement::where('connection', $second)->value('abonnement_url'))->toBe($secondUrl)
            ->and(attributedConnection($this->instance->authOf($secondUrl)))->toBe($second);
    });

    it('gives a connection that inherits main\'s url its own abonnement when it has its own client id', function () {
        $mainInstance = new FakeNotificatiesInstance(MAIN_NC, tenantScoped: true);
        fakeNotificatiesInstances($mainInstance);
        $municipality = municipalityConnection(null, ['is_oneground' => true, 'client_id' => 'tenant-a-client']);

        expect(registrar()->register('main')->name)->toBe('Created')
            ->and(registrar()->register($municipality)->name)->toBe('Created');

        $ofMain = $mainInstance->ofTenant((string) config('zgw.connections.main.client_id'));
        $ofMunicipality = $mainInstance->ofTenant('tenant-a-client');

        expect($ofMain)->toHaveCount(1)
            ->and($ofMunicipality)->toHaveCount(1)
            ->and(attributedConnection($mainInstance->authOf((string) array_key_first($ofMain))))->toBe('main')
            ->and(attributedConnection($mainInstance->authOf((string) array_key_first($ofMunicipality))))->toBe($municipality);
    });

    it('keeps one abonnement when the connections share a client id', function () {
        $first = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => 'tenant-a-client']);
        $second = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => 'tenant-a-client']);

        expect(registrar()->register($first)->name)->toBe('Created')
            ->and(registrar()->register($second)->name)->toBe('RefusedSharedInstance')
            ->and($this->instance->abonnementen)->toHaveCount(1);
    });

    it('keeps one abonnement per url for connections that are not oneground', function () {
        $plain = new FakeNotificatiesInstance(OWN_NC);
        fakeNotificatiesInstances($plain);
        $first = municipalityConnection(OWN_NC, ['client_id' => 'tenant-a-client']);
        $second = municipalityConnection(OWN_NC, ['client_id' => 'tenant-b-client']);

        expect(registrar()->register($first)->name)->toBe('Created')
            ->and(registrar()->register($second)->name)->toBe('RefusedSharedInstance')
            ->and($plain->abonnementen)->toHaveCount(1)
            ->and(app(AbonnementHealthCheck::class)->run($second)->status->name)->toBe('NotificatiesInstanceInUse');
    });

    it('gives each connection of a mixed pair its own abonnement, health and renewal', function () {
        $plain = municipalityConnection(OWN_NC, ['client_id' => 'tenant-a-client']);
        $oneground = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => 'tenant-b-client']);

        expect(registrar()->register($plain)->name)->toBe('Created')
            ->and(registrar()->register($oneground)->name)->toBe('Created');

        ZgwAbonnement::query()->update(['expires_at' => now()->addMonths(6)]);

        expect(app(AbonnementHealthCheck::class)->run($plain)->status->name)->toBe('Healthy')
            ->and(app(AbonnementHealthCheck::class)->run($oneground)->status->name)->toBe('Healthy');

        ZgwAbonnement::query()->update(['expires_at' => now()->addDay()]);
        $tokensBefore = ZgwAbonnement::query()->pluck('token_id', 'connection')->all();

        (new RenewZgwAbonnementen)->handle(app(WebhookTokenIssuer::class), registrar());

        expect(ZgwAbonnement::where('connection', $plain)->value('token_id'))->not->toBe($tokensBefore[$plain])
            ->and(ZgwAbonnement::where('connection', $oneground)->value('token_id'))->not->toBe($tokensBefore[$oneground]);
    });

    it('treats a mixed pair the same whichever connection registers first', function () {
        $oneground = municipalityConnection(OWN_NC, ['is_oneground' => true, 'client_id' => 'tenant-b-client']);
        $plain = municipalityConnection(OWN_NC, ['client_id' => 'tenant-a-client']);

        expect(registrar()->register($oneground)->name)->toBe('Created')
            ->and(registrar()->register($plain)->name)->toBe('Created');
    });

    it('falls back to one abonnement when the flag is set for an instance that lists every abonnement', function () {
        // A plain Notificaties API shows every client's abonnementen, so the
        // second registration finds main's abonnement on our callback.
        $plain = new FakeNotificatiesInstance(MAIN_NC);
        fakeNotificatiesInstances($plain);
        $municipality = municipalityConnection(null, ['is_oneground' => true, 'client_id' => 'tenant-b-client']);

        expect(registrar()->register('main')->name)->toBe('Created')
            ->and(registrar()->register($municipality)->name)->toBe('RefusedSharedInstance')
            ->and($plain->abonnementen)->toHaveCount(1)
            ->and(attributedConnection($plain->authOf((string) array_key_first($plain->abonnementen))))->toBe('main')
            ->and(app(AbonnementHealthCheck::class)->run($municipality)->status->name)->toBe('NoLocalRecord');
    });
});
