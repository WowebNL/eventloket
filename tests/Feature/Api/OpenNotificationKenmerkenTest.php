<?php

declare(strict_types=1);

use App\Jobs\ProcessOpenNotification;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;

beforeEach(function () {
    Config::set('openzaak.url', 'https://example.com/');

    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);

    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
        'scope' => 'notifications:receive',
    ]);

    $this->accessToken = $response->json('access_token');

    Queue::fake([ProcessOpenNotification::class]);
});

/**
 * The notification carried by the queued job.
 */
function queuedNotification(ProcessOpenNotification $job): OpenNotification
{
    /** @var OpenNotification $notification */
    $notification = (new ReflectionProperty($job, 'notification'))->getValue($job);

    return $notification;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function postNotification(mixed $test, array $overrides = []): TestResponse
{
    return $test->postJson(route('api.open-notifications.listen'), array_merge([
        'actie' => 'create',
        'kanaal' => 'documenten',
        'resource' => 'enkelvoudiginformatieobject',
        'hoofdObject' => 'https://example.com/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        'resourceUrl' => 'https://example.com/documenten/api/v1/enkelvoudiginformatieobjecten/1',
        'aanmaakdatum' => now()->toIso8601String(),
    ], $overrides), [
        'Authorization' => 'Bearer '.$test->accessToken,
    ]);
}

test('the kenmerken of an incoming notification reach the queued job', function () {
    postNotification($this, [
        'kenmerken' => [
            'bronorganisatie' => '111111110',
            'informatieobjecttype' => 'https://example.com/catalogi/api/v1/informatieobjecttypen/1',
        ],
    ])->assertOk();

    Queue::assertPushed(
        ProcessOpenNotification::class,
        fn (ProcessOpenNotification $job): bool => queuedNotification($job)->kenmerk('bronorganisatie') === '111111110'
            && queuedNotification($job)->kenmerken['informatieobjecttype'] === 'https://example.com/catalogi/api/v1/informatieobjecttypen/1',
    );
});

test('a notification without kenmerken is accepted and queued with none', function () {
    postNotification($this)->assertOk();

    Queue::assertPushed(
        ProcessOpenNotification::class,
        fn (ProcessOpenNotification $job): bool => queuedNotification($job)->kenmerken === []
            && queuedNotification($job)->kenmerk('bronorganisatie') === null,
    );
});

test('a kenmerk value we cannot match on is dropped rather than rejecting the notification', function () {
    postNotification($this, [
        'kenmerken' => [
            'bronorganisatie' => '111111110',
            'nested' => ['not' => 'a scalar'],
        ],
    ])->assertOk();

    Queue::assertPushed(
        ProcessOpenNotification::class,
        fn (ProcessOpenNotification $job): bool => queuedNotification($job)->kenmerken === ['bronorganisatie' => '111111110'],
    );
});
