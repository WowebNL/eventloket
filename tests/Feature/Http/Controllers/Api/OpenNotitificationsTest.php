<?php

use App\Jobs\DocumentNotificationReceived;
use App\Jobs\Zaak\ClearZaakCache;
use App\Jobs\ZaakStatusNotificationReceived;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Client;

beforeEach(function () {
    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);

    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
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
        ClearZaakCache::class,
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
    Queue::assertPushed(ClearZaakCache::class);

});

test('Open notifications endpoint handles zaak status changed notification', function () {
    Queue::fake([
        ZaakStatusNotificationReceived::class,
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
    Queue::assertPushed(ZaakStatusNotificationReceived::class);

});

test('Open notifications endpoint handles document creation notification', function () {
    Queue::fake([
        DocumentNotificationReceived::class,
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
    Queue::assertPushed(DocumentNotificationReceived::class);

});

test('Open notifications endpoint handles document update notification', function () {
    Queue::fake([
        DocumentNotificationReceived::class,
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
    Queue::assertPushed(DocumentNotificationReceived::class);

});
