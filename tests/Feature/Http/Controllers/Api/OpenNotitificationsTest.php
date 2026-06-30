<?php

use App\Jobs\ProcessOpenNotification;
use App\Models\MunicipalityZgwConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Client;

beforeEach(function () {
    // Bestaande tests gebruiken example.com als host; de host-validatie
    // in OpenNotificationRequest controleert tegen deze waarde.
    Config::set('openzaak.url', 'https://example.com/');

    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);

    // The listen webhook requires the notifications:receive scope, so the token
    // is requested with that scope (see EnsureClientIsResourceOwner on the route).
    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
        'scope' => 'notifications:receive',
    ]);

    $body = $response->json();
    $this->access_token = $body['access_token'];
});

test('Open notifications endpoint is protected', function () {
    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://example.com/zaak/123',
        'resourceUrl' => 'https://example.com/zaak/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ]);

    $response->assertStatus(401);
});

test('Open notifications endpoint is reachable with valid access key', function () {
    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://example.com/zaak/123',
        'resourceUrl' => 'https://example.com/zaak/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(200);
});

// De `CreateZaak`-notificatie-tak is verwijderd: in de nieuwe Filament-
// submit-flow maken wij zelf de zaak aan, dus er komt geen OpenZaak-
// webhook meer binnen die een `CreateZaak`-keten hoeft te starten.
// Status-, eigenschap- en document-notificaties blijven wel werken
// omdat die ook bij onze eigen zaken kunnen voorkomen.

test('Open notifications endpoint handles update zaak eigenschap notification', function () {
    Queue::fake([
        ProcessOpenNotification::class,
    ]);

    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'partial_update',
        'kanaal' => 'zaken',
        'resource' => 'zaakeigenschap',
        'hoofdObject' => 'https://example.com/zaak/123',
        'resourceUrl' => 'https://example.com/zaakeigenschap/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(200);
    Queue::assertPushed(ProcessOpenNotification::class);

});

test('Open notifications endpoint handles zaak status changed notification', function () {
    Queue::fake([
        ProcessOpenNotification::class,
    ]);

    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'status',
        'hoofdObject' => 'https://example.com/zaak/123',
        'resourceUrl' => 'https://example.com/status/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(200);
    Queue::assertPushed(ProcessOpenNotification::class);

});

test('Open notifications endpoint handles document creation notification', function () {
    Queue::fake([
        ProcessOpenNotification::class,
    ]);

    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'documenten',
        'resource' => 'enkelvoudiginformatieobject',
        'hoofdObject' => 'https://example.com/zaak/123',
        'resourceUrl' => 'https://example.com/enkelvoudiginformatieobject/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(200);
    Queue::assertPushed(ProcessOpenNotification::class);

});

test('Open notifications endpoint handles document update notification', function () {
    Queue::fake([
        ProcessOpenNotification::class,
    ]);

    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'partial_update',
        'kanaal' => 'documenten',
        'resource' => 'enkelvoudiginformatieobject',
        'hoofdObject' => 'https://example.com/zaak/123',
        'resourceUrl' => 'https://example.com/enkelvoudiginformatieobject/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(200);
    Queue::assertPushed(ProcessOpenNotification::class);

});

test('hoofdObject met vreemde host wordt geweigerd (SSRF-bescherming)', function () {
    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'http://169.254.169.254/latest/meta-data/',
        'resourceUrl' => 'https://example.com/zaak/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['hoofdObject']);
});

test('resourceUrl met vreemde host wordt geweigerd (SSRF-bescherming)', function () {
    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://example.com/zaak/123',
        'resourceUrl' => 'http://internal.attacker.com/steal-credentials',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['resourceUrl']);
});

test('een notificatie van een eigen gemeente-host wordt geaccepteerd', function () {
    Queue::fake([ProcessOpenNotification::class]);

    // The connection's own host is not the global OpenZaak host, but it is a
    // trusted ZGW host, so its notifications must be accepted.
    MunicipalityZgwConnection::factory()->create();

    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://gemeente.example.com/zaken/api/v1/zaken/123',
        'resourceUrl' => 'https://gemeente.example.com/zaken/api/v1/zaken/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(200);
    Queue::assertPushed(ProcessOpenNotification::class);
});

test('een ontvangen notificatie landt in het ZGW-logboek van de gemeente', function () {
    Queue::fake([ProcessOpenNotification::class]);

    // A per-municipality connection whose host uniquely identifies the gemeente,
    // so the notification is attributed to that municipality's logboek.
    $connection = MunicipalityZgwConnection::factory()->create();
    $name = "gemeente_{$connection->municipality_id}";

    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'status',
        'hoofdObject' => 'https://gemeente.example.com/zaken/api/v1/zaken/123',
        'resourceUrl' => 'https://gemeente.example.com/zaken/api/v1/statussen/456?foo=bar',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(200);

    // The query string is stripped (it can carry personal data), as for outbound logs.
    $this->assertDatabaseHas('zgw_request_logs', [
        'connection' => $name,
        'municipality_id' => $connection->municipality_id,
        'method' => 'NOTIFY',
        'resource' => '/zaken/api/v1/statussen/456',
        'failed' => false,
    ]);
});
