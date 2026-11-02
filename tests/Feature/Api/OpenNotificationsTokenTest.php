<?php

use App\Jobs\ProcessOpenNotification;
use App\Models\Application;
use App\Models\ZgwWebhookIngressState;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    config()->set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $application = Application::factory()->create();

    $this->notificationsClient = Client::create([
        'owner_type' => Application::class,
        'owner_id' => $application->id,
        'name' => 'open-notificaties',
        'secret' => 'test-secret',
        'grant_types' => ['client_credentials'],
        'redirect_uris' => [],
        'revoked' => false,
    ]);
});

function validNotificationPayload(): array
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
 * An Authorization header carrying a JWT whose token id this application issued.
 * Only the payload matters here; the signature is never checked by the ingress
 * bookkeeping, and the tests that need authentication stub the resource server.
 */
function knownIngressCredential(array $tokenAttributes = []): string
{
    $token = Token::create(array_merge([
        'id' => Str::random(80),
        'user_id' => null,
        'client_id' => (string) Str::uuid(),
        'name' => WebhookTokenIssuer::APPLICATION_NAME,
        'scopes' => [WebhookTokenIssuer::SCOPE],
        'revoked' => false,
        'expires_at' => now()->addYear(),
    ], $tokenAttributes));

    $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

    return 'Bearer '.$segment(['typ' => 'JWT', 'alg' => 'RS256']).'.'.$segment(['jti' => $token->getKey()]).'.signature';
}

/**
 * Make every query against the ingress state fail, as a lock timeout or a
 * dropped connection would.
 */
function failIngressBookkeeping(): void
{
    DB::beforeExecuting(function (string $query): void {
        if (str_contains($query, 'zgw_webhook_ingress_states')) {
            throw new RuntimeException('Simulated ingress bookkeeping failure.');
        }
    });
}

test('a token with the notifications:receive scope can call the listen endpoint', function () {
    Queue::fake();
    Passport::actingAsClient($this->notificationsClient, ['notifications:receive']);

    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(200);

    Queue::assertPushed(ProcessOpenNotification::class);
});

test('a token without the notifications:receive scope cannot call the listen endpoint', function () {
    Passport::actingAsClient($this->notificationsClient, []);

    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(403);
});

test('a token with only api:access cannot call the listen endpoint', function () {
    Passport::actingAsClient($this->notificationsClient, ['api:access']);

    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(403);
});

test('the wildcard scope can call the listen endpoint', function () {
    Queue::fake();
    Passport::actingAsClient($this->notificationsClient, ['*']);

    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(200);
});

test('an unauthenticated request is blocked on the listen endpoint', function () {
    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(401);
});

test('logs an unauthenticated webhook rejection', function () {
    Log::spy();

    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(401);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => $message === 'Rejected Open Notificaties webhook call.'
            && ($context['category'] ?? null) === 'authentication')
        ->once();
});

test('logs an insufficient-scope webhook rejection', function () {
    Log::spy();
    Passport::actingAsClient($this->notificationsClient, ['api:access']);

    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(403);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => $message === 'Rejected Open Notificaties webhook call.'
            && ($context['category'] ?? null) === 'authorization')
        ->once();
});

test('logs an ssrf host-rule webhook rejection', function () {
    Log::spy();
    Passport::actingAsClient($this->notificationsClient, ['notifications:receive']);

    $payload = validNotificationPayload();
    $payload['hoofdObject'] = 'https://evil.example.com/zaken/api/v1/zaken/uuid';
    $payload['resourceUrl'] = 'https://evil.example.com/zaken/api/v1/zaken/uuid';

    $this->postJson('/api/open-notifications/listen', $payload)
        ->assertStatus(422);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => $message === 'Rejected Open Notificaties webhook call.'
            && ($context['category'] ?? null) === 'validation')
        ->once();
});

test('an accepted notification stamps the ingress as working', function () {
    Queue::fake();
    Passport::actingAsClient($this->notificationsClient, ['notifications:receive']);

    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(200);

    expect(ZgwWebhookIngressState::current()->last_accepted_at)->not->toBeNull();
});

test('a rejection of a credential this application issued is counted', function () {
    Passport::actingAsClient($this->notificationsClient, ['api:access']);

    // actingAsClient stubs the resource server rather than the transport, so the
    // header a real caller always sends has to be stated here: the token it
    // carries is what separates our own sender from anything else.
    $this->postJson('/api/open-notifications/listen', validNotificationPayload(), [
        'Authorization' => knownIngressCredential(),
    ])->assertStatus(403);

    $state = ZgwWebhookIngressState::current();

    expect($state->consecutive_rejections)->toBe(1)
        ->and($state->last_rejection_status)->toBe(403)
        ->and($state->rejection_streak_started_at)->not->toBeNull();
});

test('an unauthenticated probe is logged but not counted', function () {
    // A public endpoint collects probes as a matter of course. Counting them
    // would make the health signal fire on background noise.
    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(401);

    expect(ZgwWebhookIngressState::current())->toBeNull();
});

test('a 401 for a credential this application issued is counted', function () {
    // A revoked token of our own is exactly the standing refusal the health
    // check has to see.
    $this->postJson('/api/open-notifications/listen', validNotificationPayload(), [
        'Authorization' => knownIngressCredential(['revoked' => true]),
    ])->assertStatus(401);

    expect(ZgwWebhookIngressState::current()->consecutive_rejections)->toBe(1);
});

test('a 401 for an arbitrary authorization header is not counted', function () {
    // Presenting some header is not the same as presenting our credential, so
    // it must not move the health signal.
    foreach (['Bearer not-a-valid-token', 'Basic dXNlcjpwYXNz', 'Bearer a.b.c'] as $header) {
        $this->postJson('/api/open-notifications/listen', validNotificationPayload(), [
            'Authorization' => $header,
        ])->assertStatus(401);
    }

    expect(ZgwWebhookIngressState::current())->toBeNull();
});

test('a 401 for a well-formed token this application never issued is not counted', function () {
    $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');
    $foreign = $segment(['typ' => 'JWT', 'alg' => 'RS256']).'.'.$segment(['jti' => Str::random(80)]).'.signature';

    $this->postJson('/api/open-notifications/listen', validNotificationPayload(), [
        'Authorization' => 'Bearer '.$foreign,
    ])->assertStatus(401);

    expect(ZgwWebhookIngressState::current())->toBeNull();
});

test('a single client address is throttled on the listen endpoint', function () {
    // The limit is stated outright rather than read from the route, so changing
    // it is a deliberate act.
    for ($call = 1; $call <= 120; $call++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/open-notifications/listen', validNotificationPayload(), [
                'Authorization' => 'Bearer not-a-valid-token',
            ])->assertStatus(401);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/open-notifications/listen', validNotificationPayload(), [
            'Authorization' => 'Bearer not-a-valid-token',
        ])->assertStatus(429);

    // Another client address has its own allowance.
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
        ->postJson('/api/open-notifications/listen', validNotificationPayload(), [
            'Authorization' => 'Bearer not-a-valid-token',
        ])->assertStatus(401);
});

test('a throttled call on the listen endpoint is logged with only its address and path', function () {
    Log::spy();

    for ($call = 1; $call <= 120; $call++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
            ->postJson('/api/open-notifications/listen', validNotificationPayload(), [
                'Authorization' => 'Bearer not-a-valid-token',
            ])->assertStatus(401);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
        ->postJson('/api/open-notifications/listen', validNotificationPayload(), [
            'Authorization' => 'Bearer not-a-valid-token',
        ])->assertStatus(429);

    // A limit that turns out too low has to be visible here; the exact context
    // also pins that no header or credential ends up in the log.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => $message === 'Throttled Open Notificaties webhook call.'
            && $context === ['ip' => '203.0.113.30', 'path' => 'api/open-notifications/listen'])
        ->once();
});

test('a failure to record an accepted notification does not fail the delivery', function () {
    Queue::fake();
    Log::spy();
    Passport::actingAsClient($this->notificationsClient, ['notifications:receive']);
    failIngressBookkeeping();

    // The notification is already queued when the bookkeeping runs. Answering
    // with an error would make the sender deliver it again.
    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(200);

    Queue::assertPushed(ProcessOpenNotification::class, 1);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'Failed to record an accepted notification on the webhook ingress state.')
        ->once();
});

test('a failure to record a rejection does not change the rejection', function () {
    Log::spy();
    Passport::actingAsClient($this->notificationsClient, ['api:access']);
    $credential = knownIngressCredential();
    failIngressBookkeeping();

    $this->postJson('/api/open-notifications/listen', validNotificationPayload(), [
        'Authorization' => $credential,
    ])->assertStatus(403);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'Failed to record a rejected call on the webhook ingress state.')
        ->once();
});

test('an accepted notification clears an earlier rejection streak', function () {
    Queue::fake();

    ZgwWebhookIngressState::recordRejection(403);
    ZgwWebhookIngressState::recordRejection(403);

    Passport::actingAsClient($this->notificationsClient, ['notifications:receive']);

    $this->postJson('/api/open-notifications/listen', validNotificationPayload())
        ->assertStatus(200);

    $state = ZgwWebhookIngressState::current();

    expect($state->consecutive_rejections)->toBe(0)
        ->and($state->rejection_streak_started_at)->toBeNull();
});
