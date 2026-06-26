<?php

use App\Jobs\ProcessOpenNotification;
use App\Models\Application;
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
