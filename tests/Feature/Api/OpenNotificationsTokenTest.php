<?php

use App\Models\Application;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

beforeEach(function () {
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

    $this->otherClient = Client::create([
        'owner_type' => Application::class,
        'owner_id' => $application->id,
        'name' => 'other-client',
        'secret' => 'test-secret',
        'grant_types' => ['client_credentials'],
        'redirect_uris' => [],
        'revoked' => false,
    ]);
});

test('token met notifications:receive scope kan de listen endpoint aanroepen', function () {
    Passport::actingAsClient($this->notificationsClient, ['notifications:receive']);

    $response = $this->postJson('/api/open-notifications/listen', [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://example.com/zaken/uuid',
        'resourceUrl' => 'https://example.com/zaken/uuid',
        'aanmaakdatum' => now()->toDateTimeString(),
    ]);

    // 200 or 202 means the token was accepted and the request reached the controller
    $response->assertStatus(200);
});

test('token zonder scope kan de listen endpoint niet aanroepen', function () {
    Passport::actingAsClient($this->notificationsClient, []);

    $response = $this->postJson('/api/open-notifications/listen', [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://example.com/zaken/uuid',
        'resourceUrl' => 'https://example.com/zaken/uuid',
        'aanmaakdatum' => now()->toDateTimeString(),
    ]);

    $response->assertStatus(403);
});

test('token met notifications:receive scope wordt geblokkeerd op andere api endpoints', function () {
    // De algemene routes vereisen de scope api:access.
    // Een token met alleen notifications:receive heeft die scope niet → 403.
    Passport::actingAsClient($this->notificationsClient, ['notifications:receive']);

    $response = $this->postJson('/api/events/check', [
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(403);
});

test('token met api:access scope kan andere api endpoints aanroepen', function () {
    Passport::actingAsClient($this->otherClient, ['api:access']);

    $response = $this->postJson('/api/events/check', [
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
        'municipality' => 'GM0123',
    ]);

    // Auth middleware passeert; controller geeft 422 terug omdat de gemeente niet bestaat.
    $response->assertStatus(422);
});

test('unauthenticated request wordt geblokkeerd op de listen endpoint', function () {
    $response = $this->postJson('/api/open-notifications/listen', [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://example.com/zaken/uuid',
        'resourceUrl' => 'https://example.com/zaken/uuid',
        'aanmaakdatum' => now()->toDateTimeString(),
    ]);

    $response->assertStatus(401);
});
