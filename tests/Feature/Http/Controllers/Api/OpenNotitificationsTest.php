<?php

use App\Events\OpenNotification\CreateZaakNotificationReceived;
use App\Jobs\Zaak\AddEinddatumZGW;
use App\Jobs\Zaak\AddGeometryZGW;
use App\Jobs\Zaak\AddZaakeigenschappenZGW;
use App\Jobs\Zaak\ClearZaakCache;
use App\Jobs\Zaak\CreateZaak;
use App\Jobs\Zaak\UpdateInitiatorZGW;
use App\Listeners\Zaak\ProcessCreateZaak;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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

test('Open notifications endpoint handles create zaak notification', function () {
    Event::fake([
        CreateZaakNotificationReceived::class,
    ]);

    Config::set('openzaak.objectsapi.url', 'https://example.com');
    Http::fake([
        'https://example.com/zaak/123' => Http::response([
            'url' => 'https://example.com/zaak/123',
            'identificatie' => 'ZAAK-123',
            'omschrijving' => 'Test zaak',
            'zaaktype' => 'https://example.com/zaaktype/1',
            'status' => 'https://example.com/status/1',
            'startdatum' => now()->toIso8601String(),
            'einddatum' => null,
            'betrokkene' => [],
            'object' => 'https://example.com/zaakobject/1',
            'zaakobject' => 'https://example.com/zaakobject/1',
            'resultaat' => null,
            'bronorganisatie' => 'https://example.com/organisatie/1',
            'doelorganisatie' => null,
            'toelichting' => 'This is a test zaak',
        ], 200),
    ]);

    $response = $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'create',
        'kanaal' => 'zaken',
        'resource' => 'zaakobject',
        'hoofdObject' => 'https://example.com/zaak/123',
        'resourceUrl' => 'https://example.com/zaak/123',
        'aanmaakdatum' => now()->toIso8601String(),
    ], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(200);

    // check if event is dispatched
    Event::assertDispatched(CreateZaakNotificationReceived::class);
    // check if the event is listened by the correct listener
    Event::assertListening(
        CreateZaakNotificationReceived::class,
        ProcessCreateZaak::class
    );

});

test('Create zaak notifications dispatches correct jobs in bus chain', function () {
    Bus::fake();

    $listener = new ProcessCreateZaak;
    $listener->handle(new CreateZaakNotificationReceived(
        new OpenNotification(...[
            'actie' => 'create',
            'kanaal' => 'zaken',
            'resource' => 'zaakobject',
            'hoofdObject' => 'https://example.com/zaak/123',
            'resourceUrl' => 'https://example.com/zaak/123',
            'aanmaakdatum' => now()->toIso8601String(),
        ])
    ));

    Bus::assertChained([
        AddZaakeigenschappenZGW::class,
        AddEinddatumZGW::class,
        UpdateInitiatorZGW::class,
        AddGeometryZGW::class,
        CreateZaak::class,
    ]);
});

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
