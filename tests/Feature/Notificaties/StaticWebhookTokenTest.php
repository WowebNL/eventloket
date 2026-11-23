<?php

declare(strict_types=1);

use App\Exceptions\NotificatiesHealthException;
use App\Jobs\Notificaties\RenewZgwAbonnementen;
use App\Jobs\ProcessOpenNotification;
use App\Models\Application;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\ZgwAbonnement;
use App\Models\ZgwRequestLog;
use App\Models\ZgwWebhookIngressState;
use App\Models\ZgwWebhookToken;
use App\Services\Notificaties\AbonnementCheckStatus;
use App\Services\Notificaties\AbonnementHealthCheck;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\InvalidWebhookAuthException;
use App\Services\Notificaties\StaticWebhookTokenIssuer;
use App\Services\Notificaties\WebhookAuthType;
use App\Services\Notificaties\WebhookTokenIssuer;
use App\Services\Zgw\ZgwConnectionResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\Fakes\ZgwHttpFake;

const SWT_NC = 'https://nc.example.com/api/v1/';
const SWT_ABO_URL = 'https://nc.example.com/api/v1/abonnement/abc';
const SWT_LISTEN = '/api/open-notifications/listen';

beforeEach(function () {
    Cache::flush();
    config()->set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    config()->set('zgw.connections.main.urls.notificaties', SWT_NC);
    config()->set('zgw.connections.main.webhook_auth', 'static');

    // Every registration test fails loudly if a Passport token is requested.
    $this->passportRequested = false;
});

/**
 * The test notification Open Notificaties sends while it saves an abonnement.
 *
 * @return array<string, mixed>
 */
function swtTestNotification(array $overrides = []): array
{
    return array_merge([
        'kanaal' => 'test',
        'hoofdObject' => 'http://some.hoofdobject.nl/',
        'resource' => 'test',
        'resourceUrl' => 'http://some.resource.nl/',
        'actie' => 'create',
        'aanmaakdatum' => now()->toIso8601String(),
        'kenmerken' => [],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function swtNotification(): array
{
    $object = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/uuid';

    return [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => $object,
        'resourceUrl' => $object,
        'aanmaakdatum' => now()->toDateTimeString(),
    ];
}

/**
 * A Notificaties API that behaves like Open Notificaties: while it handles a
 * create or update it sends the test notification to our webhook with the
 * `auth` value from the request, and refuses the abonnement unless the webhook
 * answers with a 2xx. The statuses the webhook gave are collected.
 *
 * @param  array<string, mixed>|null  $existing  the abonnement GET returns, null for none
 */
function swtFakeOpenNotificaties(?array $existing = null, ?Closure $override = null): ArrayObject
{
    $callbacks = new ArrayObject;

    $validate = function (Request $request) use ($callbacks): ?int {
        $status = test()->postJson(SWT_LISTEN, swtTestNotification(), ['Authorization' => (string) $request['auth']])->status();
        $callbacks->append($status);

        return $status;
    };

    Http::fake([
        rtrim((string) config('app.url'), '/').'/oauth/token' => function () {
            test()->passportRequested = true;

            $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

            return Http::response([
                'access_token' => $segment(['typ' => 'JWT', 'alg' => 'RS256']).'.'.$segment(['jti' => 'passport-'.Str::random(8)]).'.signature',
                'token_type' => 'Bearer',
                'expires_in' => 31536000,
            ], 200);
        },
        SWT_NC.'abonnement' => function (Request $request) use ($validate, $override) {
            if ($request->method() === 'GET') {
                return Http::response(['count' => 0, 'next' => null, 'previous' => null, 'results' => []], 200);
            }

            if ($override !== null) {
                return $override($request);
            }

            $status = $validate($request);

            return $status >= 200 && $status <= 209
                ? Http::response(['url' => SWT_ABO_URL], 201)
                : Http::response(['callbackUrl' => ['De callback URL kan geen notificaties ontvangen.']], 400);
        },
        SWT_ABO_URL => function (Request $request) use ($validate, $existing, $override) {
            if ($request->method() === 'GET') {
                return $existing === null
                    ? Http::response(['detail' => 'Niet gevonden.'], 404)
                    : Http::response($existing, 200);
            }

            if ($override !== null) {
                return $override($request);
            }

            $status = $validate($request);

            return $status >= 200 && $status <= 209
                ? Http::response(['url' => SWT_ABO_URL], 200)
                : Http::response(['callbackUrl' => ['De callback URL kan geen notificaties ontvangen.']], 400);
        },
    ]);

    return $callbacks;
}

function swtRemoteAbonnement(): array
{
    return [
        'url' => SWT_ABO_URL,
        'callbackUrl' => 'https://eventloket.example.com/api/open-notifications/listen',
        'kanalen' => array_map(fn (string $naam): array => ['naam' => $naam, 'filters' => []], AbonnementRegistrar::KANALEN),
    ];
}

/**
 * The plaintext token the last create or patch carried.
 */
function swtSentToken(): string
{
    $sent = Http::recorded(fn (Request $request): bool => in_array($request->method(), ['POST', 'PATCH'], true)
        && str_starts_with($request->url(), SWT_NC.'abonnement'))->last();

    return (string) preg_replace('/^Bearer /', '', (string) $sent[0]['auth']);
}

/**
 * Register main with a static token directly in storage, as a completed
 * registration would leave it.
 *
 * @return array{token: string, row: ZgwWebhookToken, abonnement: ZgwAbonnement}
 */
function swtRegisteredStatic(string $connection = 'main'): array
{
    $issuer = app(StaticWebhookTokenIssuer::class);
    $token = $issuer->issue($connection);
    $issuer->activate($token);

    $abonnement = ZgwAbonnement::factory()->create([
        'connection' => $connection,
        'municipality_id' => ZgwAbonnement::municipalityIdFromConnection($connection),
        'notificaties_base_url' => SWT_NC,
        'abonnement_url' => SWT_ABO_URL,
        'auth_type' => 'static',
        'token_id' => null,
        'client_id' => null,
        'expires_at' => $token->expiresAt,
    ]);

    return ['token' => $token->token, 'row' => ZgwWebhookToken::findOrFail($token->staticTokenId), 'abonnement' => $abonnement];
}

/**
 * The connection the queued notification was attributed to.
 */
function swtQueuedSender(): ?string
{
    $sender = null;

    Queue::assertPushed(ProcessOpenNotification::class, function (ProcessOpenNotification $job) use (&$sender): bool {
        $sender = (fn () => $this->notification)->call($job)->senderConnection;

        return true;
    });

    return $sender;
}

// --- issuing --------------------------------------------------------------

test('registering a static connection stores only the hash of a short token', function () {
    $callbacks = swtFakeOpenNotificaties();

    expect(app(AbonnementRegistrar::class)->register('main')->name)->toBe('Created');

    $token = swtSentToken();
    $abonnement = ZgwAbonnement::where('connection', 'main')->firstOrFail();
    $row = ZgwWebhookToken::where('connection', 'main')->sole();

    expect($token)->toMatch(StaticWebhookTokenIssuer::PATTERN)
        ->and(strlen('Bearer '.$token))->toBeLessThan(100)
        ->and(strlen('Bearer '.$token))->toBeLessThan(1000)
        ->and($abonnement->auth_type)->toBe('static')
        ->and($abonnement->token_id)->toBeNull()
        ->and($abonnement->client_id)->toBeNull()
        ->and($abonnement->expires_at->isAfter(now()->addMonths(11)))->toBeTrue()
        ->and($row->secret_hash)->toBe(hash('sha256', explode('.', $token)[2]))
        ->and($row->expires_at->equalTo($abonnement->expires_at))->toBeTrue()
        ->and($this->passportRequested)->toBeFalse()
        ->and(Client::where('name', WebhookTokenIssuer::APPLICATION_NAME)->count())->toBe(0);

    // Nothing stored anywhere holds the secret itself.
    expect(json_encode(ZgwWebhookToken::query()->get()->makeVisible('secret_hash')->toArray()))->not->toContain(explode('.', $token)[2])
        ->and(json_encode(ZgwAbonnement::query()->get()->toArray()))->not->toContain(explode('.', $token)[2]);

    // The test notification sent during the POST already passed.
    expect($callbacks->getArrayCopy())->toBe([204]);
});

test('the new token already works while the abonnement is patched', function () {
    swtRegisteredStatic();
    $callbacks = swtFakeOpenNotificaties(existing: swtRemoteAbonnement());

    expect(app(AbonnementRegistrar::class)->register('main')->name)->toBe('Updated')
        ->and($callbacks->getArrayCopy())->toBe([204]);
});

test('the new token already works while the renewal patches the abonnement', function () {
    ['abonnement' => $abonnement] = swtRegisteredStatic();
    $abonnement->update(['expires_at' => now()->addDays(3)]);
    ZgwWebhookToken::query()->update(['expires_at' => now()->addDays(3)]);
    $callbacks = swtFakeOpenNotificaties(existing: swtRemoteAbonnement());

    dispatch_sync(new RenewZgwAbonnementen);

    $abonnement->refresh();

    expect($callbacks->getArrayCopy())->toBe([204])
        ->and($abonnement->auth_type)->toBe('static')
        ->and($abonnement->expires_at->isAfter(now()->addMonths(11)))->toBeTrue()
        ->and(ZgwWebhookToken::query()->whereNull('revoked_at')->count())->toBe(2);
});

test('a failed registration revokes the pending token', function () {
    swtFakeOpenNotificaties(override: fn () => Http::response(['detail' => 'boom'], 500));

    expect(fn () => app(AbonnementRegistrar::class)->register('main'))->toThrow(Exception::class);

    $token = swtSentToken();
    $row = ZgwWebhookToken::sole();

    expect($row->secret_hash)->toBeNull()
        ->and($row->revoked_at)->not->toBeNull()
        ->and(ZgwAbonnement::where('connection', 'main')->exists())->toBeFalse();

    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$token])->assertStatus(401);
});

test('an unknown setting issues nothing and is refused on registration', function () {
    config()->set('zgw.connections.main.webhook_auth', 'statik');
    swtFakeOpenNotificaties();

    expect(fn () => app(AbonnementRegistrar::class)->register('main'))->toThrow(InvalidWebhookAuthException::class)
        ->and(fn () => app(AbonnementRegistrar::class)->register('main', dryRun: true))->toThrow(InvalidWebhookAuthException::class);

    expect(ZgwWebhookToken::count())->toBe(0)
        ->and($this->passportRequested)->toBeFalse();

    Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['POST', 'PATCH'], true));
});

test('the main connection on passport keeps issuing passport tokens', function () {
    config()->set('zgw.connections.main.webhook_auth', 'passport');
    swtFakeOpenNotificaties(override: fn () => Http::response(['url' => SWT_ABO_URL], 201));

    app(AbonnementRegistrar::class)->register('main');

    expect($this->passportRequested)->toBeTrue()
        ->and(ZgwAbonnement::where('connection', 'main')->value('auth_type'))->toBe('passport')
        ->and(ZgwWebhookToken::count())->toBe(0);
});

test('an empty municipality column means passport even when main is static', function () {
    $connection = MunicipalityZgwConnection::factory()->active()->create(['webhook_auth' => null]);
    $name = 'gemeente_'.$connection->municipality_id;

    expect(WebhookAuthType::configuredFor($name))->toBe(WebhookAuthType::Passport)
        ->and($connection->buildConfig()['webhook_auth'])->toBe('passport');

    $connection->update(['webhook_auth' => '']);

    expect(WebhookAuthType::configuredFor($name))->toBe(WebhookAuthType::Passport)
        ->and($connection->fresh()->buildConfig()['webhook_auth'])->toBe('passport');

    $connection->update(['webhook_auth' => 'static']);

    expect(WebhookAuthType::configuredFor($name))->toBe(WebhookAuthType::Static)
        ->and($connection->fresh()->buildConfig()['webhook_auth'])->toBe('static');
});

test('an unknown municipality setting does not send its traffic to main', function () {
    $connection = MunicipalityZgwConnection::factory()->active()->create(['webhook_auth' => 'bogus']);
    $municipality = Municipality::findOrFail($connection->municipality_id);

    expect(app(ZgwConnectionResolver::class)->forMunicipality($municipality))->toBe('gemeente_'.$connection->municipality_id)
        ->and(WebhookAuthType::configuredFor('gemeente_'.$connection->municipality_id))->toBeNull();
});

test('the overlap is capped at fifteen minutes and the lifetime at a year', function () {
    config()->set('zgw.webhook.static_token_overlap_minutes', 600);
    config()->set('zgw.webhook.static_token_lifetime_days', 5000);

    expect(StaticWebhookTokenIssuer::overlapMinutes())->toBe(15)
        ->and(StaticWebhookTokenIssuer::lifetimeDays())->toBe(366);
});

// --- the webhook ----------------------------------------------------------

test('a valid static token is accepted and attributed to its connection', function () {
    Queue::fake();
    ['token' => $token] = swtRegisteredStatic();

    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$token])->assertStatus(200);

    expect(swtQueuedSender())->toBe('main');
});

test('every refused static token gets the same 401 as an invalid passport token', function () {
    ['token' => $token, 'row' => $row] = swtRegisteredStatic();
    [, $lookup, $secret] = explode('.', $token);

    $passport = $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer not-a-valid-token']);
    $passport->assertStatus(401);

    $expired = app(StaticWebhookTokenIssuer::class)->issue('main');
    ZgwWebhookToken::whereKey($expired->staticTokenId)->update(['expires_at' => now()->subMinute()]);

    $revoked = app(StaticWebhookTokenIssuer::class)->issue('main');
    app(StaticWebhookTokenIssuer::class)->discard($revoked);

    $refused = [
        'wrong secret' => 'elw.'.$lookup.'.'.str_repeat('A', 43),
        'unknown lookup' => 'elw.'.str_repeat('0', 32).'.'.$secret,
        'malformed' => 'elw.'.$lookup,
        'uppercase lookup' => 'elw.'.strtoupper($lookup).'.'.$secret,
        'expired' => $expired->token,
        'revoked' => $revoked->token,
    ];

    $comparableHeaders = fn ($response): array => collect($response->headers->all())
        ->except(['date', 'x-ratelimit-remaining'])
        ->all();

    foreach ($refused as $case => $bearer) {
        $response = $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$bearer]);

        expect($response->status())->toBe(401, $case)
            ->and($response->getContent())->toBe($passport->getContent(), $case)
            ->and($comparableHeaders($response))->toBe($comparableHeaders($passport), $case);
    }

    // The genuine token still works afterwards.
    Queue::fake();
    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$token])->assertStatus(200);
});

test('a static token keeps working after its connection is set back to passport', function () {
    Queue::fake();
    ['token' => $token] = swtRegisteredStatic();
    config()->set('zgw.connections.main.webhook_auth', 'passport');

    // Only the token row decides; the setting takes effect on registration.
    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$token])->assertStatus(200);
});

test('a static token of a connection with an unknown setting is refused without a server error', function () {
    Log::spy();
    ['token' => $token] = swtRegisteredStatic();
    config()->set('zgw.connections.main.webhook_auth', 'bogus');

    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$token])->assertStatus(401);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'webhook_auth')
            && $context === ['connection' => 'main'])
        ->once();
});

test('after a rotation the previous token works until the overlap ends', function () {
    ['token' => $old, 'abonnement' => $abonnement] = swtRegisteredStatic();
    $abonnement->update(['expires_at' => now()->addDays(3)]);
    ZgwWebhookToken::query()->update(['expires_at' => now()->addDays(3)]);
    swtFakeOpenNotificaties(existing: swtRemoteAbonnement());

    dispatch_sync(new RenewZgwAbonnementen);

    $new = swtSentToken();

    expect($new)->not->toBe($old);

    Queue::fake();

    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$old])->assertStatus(200);
    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$new])->assertStatus(200);

    $this->travel(StaticWebhookTokenIssuer::overlapMinutes() + 1)->minutes();

    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$old])->assertStatus(401);
    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$new])->assertStatus(200);
});

test('switching to passport revokes the static token at once', function () {
    ['token' => $static] = swtRegisteredStatic();
    config()->set('zgw.connections.main.webhook_auth', 'passport');
    swtFakeOpenNotificaties(existing: swtRemoteAbonnement(), override: fn () => Http::response(['url' => SWT_ABO_URL], 200));

    app(AbonnementRegistrar::class)->register('main');

    expect(ZgwAbonnement::where('connection', 'main')->value('auth_type'))->toBe('passport');

    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$static])->assertStatus(401);
});

test('switching to static retires the passport client', function () {
    $application = Application::firstOrCreate(['name' => WebhookTokenIssuer::APPLICATION_NAME]);
    $client = Client::create([
        'owner_type' => Application::class,
        'owner_id' => $application->id,
        'name' => WebhookTokenIssuer::APPLICATION_NAME,
        'secret' => Str::random(40),
        'grant_types' => ['client_credentials'],
        'redirect_uris' => [],
        'revoked' => false,
    ]);

    ZgwAbonnement::factory()->create([
        'connection' => 'main',
        'notificaties_base_url' => SWT_NC,
        'abonnement_url' => SWT_ABO_URL,
        'auth_type' => 'passport',
        'token_id' => 'old-passport-token',
        'client_id' => $client->getKey(),
    ]);

    swtFakeOpenNotificaties(existing: swtRemoteAbonnement());

    app(AbonnementRegistrar::class)->register('main');

    expect(Client::find($client->getKey()))->toBeNull()
        ->and(ZgwAbonnement::where('connection', 'main')->value('auth_type'))->toBe('static');
});

test('the renewal leaves an abonnement alone whose connection switched type', function () {
    Log::spy();
    ['abonnement' => $abonnement] = swtRegisteredStatic();
    $abonnement->update(['expires_at' => now()->addDays(3)]);
    config()->set('zgw.connections.main.webhook_auth', 'passport');
    swtFakeOpenNotificaties(existing: swtRemoteAbonnement());

    dispatch_sync(new RenewZgwAbonnementen);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PATCH');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'webhook_auth')
            && ($context['registered'] ?? null) === 'static'
            && ($context['configured'] ?? null) === 'passport')
        ->once();
});

// --- the test notification ------------------------------------------------

test('the test notification gets a 204 without being processed', function () {
    Queue::fake();
    ['token' => $token] = swtRegisteredStatic();

    $this->postJson(SWT_LISTEN, swtTestNotification(), ['Authorization' => 'Bearer '.$token])->assertNoContent();

    Queue::assertNothingPushed();

    expect(ZgwRequestLog::count())->toBe(0)
        ->and(ZgwWebhookIngressState::current())->toBeNull();
});

test('a test notification about a real zaak with a destroy action is not processed', function () {
    ['token' => $token] = swtRegisteredStatic();

    // The zaak observer queues its own jobs; keep those out of the assertion.
    Queue::fake();
    $zaak = Zaak::factory()->create();
    Queue::fake();

    $this->postJson(SWT_LISTEN, swtTestNotification([
        'actie' => 'destroy',
        'resource' => 'zaak',
        'hoofdObject' => $zaak->zgw_zaak_url,
        'resourceUrl' => $zaak->zgw_zaak_url,
    ]), ['Authorization' => 'Bearer '.$token])->assertNoContent();

    Queue::assertNothingPushed();
    expect(Zaak::whereKey($zaak->getKey())->exists())->toBeTrue();
});

test('the test notification also passes with a passport token', function () {
    Queue::fake();
    $client = Client::create([
        'owner_type' => Application::class,
        'owner_id' => Application::factory()->create()->id,
        'name' => WebhookTokenIssuer::APPLICATION_NAME,
        'secret' => 'test-secret',
        'grant_types' => ['client_credentials'],
        'redirect_uris' => [],
        'revoked' => false,
    ]);
    Passport::actingAsClient($client, [WebhookTokenIssuer::SCOPE]);

    $this->postJson(SWT_LISTEN, swtTestNotification())->assertNoContent();

    Queue::assertNothingPushed();
});

test('the test notification without a valid token is refused', function () {
    $this->postJson(SWT_LISTEN, swtTestNotification())->assertStatus(401);
    $this->postJson(SWT_LISTEN, swtTestNotification(), ['Authorization' => 'Bearer elw.'.str_repeat('a', 32).'.'.str_repeat('B', 43)])->assertStatus(401);
});

test('"test" is still not an accepted channel further down', function () {
    Queue::fake();
    ['token' => $token] = swtRegisteredStatic();

    // Only the exact string short-circuits; anything else goes through the
    // validation as before.
    $this->postJson(SWT_LISTEN, swtTestNotification(['kanaal' => 'Test']), ['Authorization' => 'Bearer '.$token])->assertStatus(422);
    $this->postJson(SWT_LISTEN, swtTestNotification(['kanaal' => ['test']]), ['Authorization' => 'Bearer '.$token])->assertStatus(422);

    Queue::assertNothingPushed();
});

// --- ingress bookkeeping ----------------------------------------------------

test('a refused static token of our own counts towards the ingress health', function () {
    $revoked = app(StaticWebhookTokenIssuer::class)->issue('main');
    app(StaticWebhookTokenIssuer::class)->discard($revoked);

    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$revoked->token])->assertStatus(401);

    expect(ZgwWebhookIngressState::current()->consecutive_rejections)->toBe(1);
});

test('a static-looking token with an unknown lookup is not counted', function () {
    $this->postJson(SWT_LISTEN, swtNotification(), [
        'Authorization' => 'Bearer elw.'.str_repeat('a', 32).'.'.str_repeat('B', 43),
    ])->assertStatus(401);

    expect(ZgwWebhookIngressState::current())->toBeNull();
});

// --- secrets stay out of logs and exceptions --------------------------------

test('the token never reaches a log line, an exception message or the console', function () {
    $logged = [];
    Log::listen(function ($event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);

        foreach ($event->context as $value) {
            if ($value instanceof Throwable) {
                $logged[] = $value->getMessage();
            }
        }
    });

    // A Notificaties API that echoes the auth value in its error body.
    swtFakeOpenNotificaties(override: fn (Request $request) => Http::response([
        'invalidParams' => [['name' => 'auth', 'reason' => 'Ongeldige waarde: '.$request['auth']]],
    ], 400));

    $thrown = null;

    try {
        app(AbonnementRegistrar::class)->register('main');
    } catch (Throwable $e) {
        $thrown = $e;
    }

    $token = swtSentToken();
    $secret = explode('.', $token)[2];

    expect($thrown)->not->toBeNull()
        ->and($thrown->getMessage())->not->toContain($secret)
        // The client truncates the body in its message, which can cut the
        // token anywhere; no fragment of the secret may survive either.
        ->and($thrown->getMessage())->not->toContain(substr($secret, 0, 6))
        ->and($thrown->getMessage())->not->toContain(explode('.', $token)[1])
        ->and($thrown->getMessage())->toContain('[redacted]')
        ->and($thrown->getPrevious())->toBeNull();

    $this->artisan('app:register-zgw-abonnementen --connection=main')
        ->doesntExpectOutputToContain('elw.')
        ->assertFailed();

    // The renewal path logs the failure.
    ['abonnement' => $abonnement] = swtRegisteredStatic();
    $abonnement->update(['expires_at' => now()->addDays(3)]);
    swtFakeOpenNotificaties(existing: swtRemoteAbonnement(), override: fn (Request $request) => Http::response([
        'detail' => 'Ongeldige waarde: '.$request['auth'],
    ], 400));

    dispatch_sync(new RenewZgwAbonnementen);

    $renewToken = swtSentToken();

    // And the webhook itself, accepted and refused.
    Queue::fake();
    $issued = app(StaticWebhookTokenIssuer::class)->issue('main');
    app(StaticWebhookTokenIssuer::class)->activate($issued);
    $live = $issued->token;
    $this->postJson(SWT_LISTEN, swtTestNotification(), ['Authorization' => 'Bearer '.$live]);
    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$live.'x']);

    $all = implode("\n", $logged);

    expect($logged)->not->toBe([])
        ->and($all)->not->toContain($secret)
        ->and($all)->not->toContain(explode('.', $renewToken)[2])
        ->and($all)->not->toContain(explode('.', $live)[2])
        ->and($all)->not->toContain('elw.');
});

// --- health check -----------------------------------------------------------

test('the health check judges a static abonnement on its own token', function () {
    ['row' => $row, 'abonnement' => $abonnement] = swtRegisteredStatic();
    Http::fake([SWT_ABO_URL => Http::response(swtRemoteAbonnement(), 200)]);

    $check = fn (): AbonnementCheckStatus => app(AbonnementHealthCheck::class)->run('main')->status;

    expect($check())->toBe(AbonnementCheckStatus::Healthy);

    // Set back to passport: the registered token still works, but the change
    // waits for a registration.
    config()->set('zgw.connections.main.webhook_auth', 'passport');
    expect($check())->toBe(AbonnementCheckStatus::WebhookAuthMismatch);

    config()->set('zgw.connections.main.webhook_auth', 'bogus');
    expect($check())->toBe(AbonnementCheckStatus::WebhookAuthMismatch);

    config()->set('zgw.connections.main.webhook_auth', 'static');

    app(StaticWebhookTokenIssuer::class)->retireOthers('main', null, withOverlap: false);
    expect($check())->toBe(AbonnementCheckStatus::TokenNotUsable);

    $abonnement->update(['expires_at' => null]);
    expect($check())->toBe(AbonnementCheckStatus::TokenNotUsable);

    $abonnement->update(['expires_at' => now()->subDay()]);
    expect($check())->toBe(AbonnementCheckStatus::TokenExpired);

    expect($row->fresh()->revoked_at)->not->toBeNull();
});

test('the health check reports a foreign token on a static abonnement it can read back', function () {
    swtRegisteredStatic();
    Http::fake([SWT_ABO_URL => Http::response(swtRemoteAbonnement() + [
        'auth' => 'Bearer elw.'.str_repeat('c', 32).'.'.str_repeat('D', 43),
    ], 200)]);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)->toBe(AbonnementCheckStatus::AuthMismatch);
});

test('the overview shows the token type of each local abonnement', function () {
    swtRegisteredStatic();
    Http::fake([SWT_NC.'abonnement' => Http::response([
        'count' => 1, 'next' => null, 'previous' => null,
        'results' => [swtRemoteAbonnement()],
    ], 200)]);

    $this->artisan('app:list-zgw-abonnementen --connection=main')
        ->assertSuccessful()
        ->expectsTable(
            ['Connectie', 'Abonnement-URL', 'Callback-URL', 'Kanalen', 'Token'],
            [['main', SWT_ABO_URL, 'https://eventloket.example.com/api/open-notifications/listen', implode(', ', AbonnementRegistrar::KANALEN), 'static']],
        );
});

// --- review round 2 ---------------------------------------------------------

test('a registration that dies between the patch and activation is not reported healthy', function () {
    ['token' => $old] = swtRegisteredStatic();

    // The Notificaties API accepted the new token, but the process stops before
    // it is given its full lifetime.
    $this->partialMock(StaticWebhookTokenIssuer::class, function ($mock): void {
        $mock->shouldReceive('activate')->andThrow(new RuntimeException('worker stopped'));
    });
    swtFakeOpenNotificaties(existing: swtRemoteAbonnement());

    expect(fn () => app(AbonnementRegistrar::class)->register('main'))->toThrow(RuntimeException::class);

    $this->travel(StaticWebhookTokenIssuer::PENDING_MINUTES + 1)->minutes();

    Http::fake([SWT_ABO_URL => Http::response(swtRemoteAbonnement(), 200)]);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)->toBe(AbonnementCheckStatus::TokenNotUsable);

    // The previous token still works on our side; it is the token the remote
    // side now presents that has lapsed.
    Queue::fake();
    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$old])->assertStatus(200);
});

test('a type mismatch inside the renewal window gets its own status and names the expiry', function () {
    Exceptions::fake();
    ['abonnement' => $abonnement] = swtRegisteredStatic();
    Http::fake([SWT_ABO_URL => Http::response(swtRemoteAbonnement(), 200)]);
    config()->set('zgw.connections.main.webhook_auth', 'passport');

    expect(app(AbonnementHealthCheck::class)->run('main')->status)->toBe(AbonnementCheckStatus::WebhookAuthMismatch);

    $expiresAt = now()->addDays(3)->startOfMinute();
    $abonnement->update(['expires_at' => $expiresAt]);
    ZgwWebhookToken::query()->update(['expires_at' => $expiresAt]);

    $result = app(AbonnementHealthCheck::class)->run('main');

    expect($result->status)->toBe(AbonnementCheckStatus::WebhookAuthMismatchExpiring)
        ->and($result->status->isDanger())->toBeTrue()
        ->and($result->status->isWarning())->toBeFalse();

    $this->artisan('app:check-zgw-notificaties')->assertFailed();

    Exceptions::assertReported(
        fn (NotificatiesHealthException $e): bool => str_contains($e->getMessage(), 'webhook_auth_mismatch_expiring')
            && str_contains($e->getMessage(), $expiresAt->toDateString()),
    );
});

test('deleting an abonnement through the overview revokes its static token', function () {
    ['token' => $token] = swtRegisteredStatic();
    Http::fake([
        SWT_NC.'abonnement' => Http::response([
            'count' => 1, 'next' => null, 'previous' => null,
            'results' => [swtRemoteAbonnement()],
        ], 200),
        SWT_ABO_URL => Http::response([], 204),
    ]);

    $this->artisan('app:list-zgw-abonnementen', [
        '--connection' => 'main',
        '--delete' => SWT_ABO_URL,
        '--force' => true,
    ])->assertSuccessful();

    $this->postJson(SWT_LISTEN, swtNotification(), ['Authorization' => 'Bearer '.$token])->assertStatus(401);
});

test('an issued token does not show its value when dumped or encoded', function () {
    $token = app(StaticWebhookTokenIssuer::class)->issue('main');
    $secret = explode('.', $token->token)[2];

    expect(json_encode($token))->not->toContain($secret)
        ->and(print_r($token, true))->not->toContain($secret)
        ->and(fn () => serialize($token))->toThrow(LogicException::class);

    ob_start();
    var_dump($token);
    $dumped = (string) ob_get_clean();

    expect($dumped)->not->toContain($secret)
        ->and($dumped)->toContain('[redacted]');
});
