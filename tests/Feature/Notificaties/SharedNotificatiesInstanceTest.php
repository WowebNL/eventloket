<?php

declare(strict_types=1);

use App\Models\MunicipalityZgwConnection;
use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementCheckStatus;
use App\Services\Notificaties\AbonnementHealthCheck;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\NotificatiesApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

/**
 * A minimal, stateful Notificaties API: it keeps the abonnementen it was given,
 * lists them to any caller and lets any caller patch them, and (like Open
 * Notificaties) never returns the write-only auth value.
 */
final class FakeNotificatiesInstance
{
    /** @var array<string, array<string, mixed>> */
    public array $abonnementen = [];

    private int $sequence = 0;

    public function __construct(public readonly string $base) {}

    /** Put an abonnement on the instance as if someone registered it earlier. */
    public function seed(string $url, string $callbackUrl, string $auth): void
    {
        $this->abonnementen[$url] = ['url' => $url, 'callbackUrl' => $callbackUrl, 'auth' => $auth, 'kanalen' => self::kanalen()];
    }

    public function handle(Request $request): mixed
    {
        $url = $request->url();

        if ($url === $this->base.'abonnement') {
            if ($request->method() === 'GET') {
                return Http::response([
                    'count' => count($this->abonnementen),
                    'next' => null,
                    'previous' => null,
                    'results' => array_values(array_map(self::public(...), $this->abonnementen)),
                ], 200);
            }

            $created = $this->base.'abonnement/'.(++$this->sequence);
            $this->abonnementen[$created] = [
                'url' => $created,
                'callbackUrl' => $request['callbackUrl'],
                'auth' => $request['auth'],
                'kanalen' => self::kanalen(),
            ];

            return Http::response(self::public($this->abonnementen[$created]), 201);
        }

        if (! isset($this->abonnementen[$url])) {
            return Http::response(['detail' => 'Niet gevonden.'], 404);
        }

        if ($request->method() === 'PATCH') {
            $this->abonnementen[$url] = array_merge($this->abonnementen[$url], $request->data());
        }

        return Http::response(self::public($this->abonnementen[$url]), 200);
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
        unset($abonnement['auth']);

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
 */
function municipalityConnection(?string $notificatiesUrl): string
{
    $connection = MunicipalityZgwConnection::factory()->create(['notificaties_url' => $notificatiesUrl]);
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
        ZgwAbonnement::factory()->create(['connection' => $this->municipality, 'abonnement_url' => OWN_NC.'abonnement/own']);

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
        ZgwAbonnement::factory()->create(['connection' => $this->municipality, 'abonnement_url' => OWN_NC.'abonnement/gone']);

        expect(registrar()->findRemote($this->api, $this->callback, $this->municipality)['url'] ?? null)
            ->toBe(OWN_NC.'abonnement/unclaimed');
    });
});

describe('health check', function () {
    it('alarms when two connections store the same abonnement', function () {
        $instance = new FakeNotificatiesInstance(MAIN_NC);
        fakeNotificatiesInstances($instance);
        $municipality = municipalityConnection(OWN_NC);

        $instance->seed(MAIN_NC.'abonnement/1', registrar()->callbackUrl(), 'Bearer x');
        ZgwAbonnement::factory()->create(['connection' => 'main', 'abonnement_url' => MAIN_NC.'abonnement/1']);
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
        ZgwAbonnement::factory()->create(['connection' => 'main', 'abonnement_url' => MAIN_NC.'abonnement/1', 'token_id' => 'tok-own']);

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
        ZgwAbonnement::factory()->create(['connection' => 'main', 'abonnement_url' => MAIN_NC.'abonnement/1', 'token_id' => 'tok-own']);

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
