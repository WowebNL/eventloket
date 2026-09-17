<?php

use App\Jobs\ProcessOpenNotification;
use App\Models\Application;
use App\Models\ZgwWebhookIngressState;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
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

test('a rejection from a caller holding a credential is counted', function () {
    Passport::actingAsClient($this->notificationsClient, ['api:access']);

    // actingAsClient stubs the resource server rather than the transport, so the
    // header a real caller always sends has to be stated here: the presence of
    // that header is what separates a credentialed caller from an anonymous probe.
    $this->postJson('/api/open-notifications/listen', validNotificationPayload(), [
        'Authorization' => 'Bearer stub',
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

test('a 401 from a caller presenting a credential is counted', function () {
    $this->postJson('/api/open-notifications/listen', validNotificationPayload(), [
        'Authorization' => 'Bearer not-a-valid-token',
    ])->assertStatus(401);

    expect(ZgwWebhookIngressState::current()->consecutive_rejections)->toBe(1);
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
