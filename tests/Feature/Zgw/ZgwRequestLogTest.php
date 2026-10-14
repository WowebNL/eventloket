<?php

use App\Models\Municipality;
use App\Models\User;
use App\Models\ZgwRequestLog;
use Woweb\Zgw\Events\ZgwRequestSent;

it('logs a ZGW request, deriving the municipality and stripping the query string', function () {
    $municipality = Municipality::factory()->create();

    event(new ZgwRequestSent(
        "gemeente_{$municipality->id}",
        'client-id',
        'GET',
        'https://gemeente.example.com/catalogi/api/v1/zaaktypen?identificatie=SECRET-123',
        200,
    ));

    $log = ZgwRequestLog::firstOrFail();

    expect($log->connection)->toBe("gemeente_{$municipality->id}")
        ->and($log->municipality_id)->toBe($municipality->id)
        ->and($log->method)->toBe('GET')
        // The query string (which can hold personal data) is not stored.
        ->and($log->resource)->toBe('/catalogi/api/v1/zaaktypen')
        ->and($log->status_code)->toBe(200)
        ->and($log->failed)->toBeFalse();
});

it('logs a single row per request, not once per registered listener', function () {
    // The listener is auto-registered by Laravel's event discovery. A manual
    // Event::listen on top of that would fire it twice and double every row.
    event(new ZgwRequestSent('main', 'client-id', 'GET', 'https://zgw.example.com/zaken/api/v1/zaken', 200));

    expect(ZgwRequestLog::count())->toBe(1);
});

it('records the authenticated user who triggered the call', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    event(new ZgwRequestSent('main', 'client-id', 'GET', 'https://zgw.example.com/zaken/api/v1/zaken', 200));

    expect(ZgwRequestLog::firstOrFail()->user_id)->toBe($user->id);
});

it('leaves the user null for background traffic', function () {
    event(new ZgwRequestSent('main', 'client-id', 'GET', 'https://zgw.example.com/zaken/api/v1/zaken', 200));

    expect(ZgwRequestLog::firstOrFail()->user_id)->toBeNull();
});

it('flags a failed request and leaves the main connection unattributed', function () {
    event(new ZgwRequestSent('main', 'client-id', 'POST', 'https://zgw.example.com/zaken/api/v1/zaken', 500));

    $log = ZgwRequestLog::firstOrFail();

    expect($log->municipality_id)->toBeNull()
        ->and($log->failed)->toBeTrue()
        ->and($log->status_code)->toBe(500);
});

it('derives the municipality id only from a gemeente connection name', function () {
    expect(ZgwRequestLog::municipalityIdFromConnection('gemeente_42'))->toBe(42)
        ->and(ZgwRequestLog::municipalityIdFromConnection('main'))->toBeNull()
        ->and(ZgwRequestLog::municipalityIdFromConnection('gemeente_'))->toBeNull();
});

it('prunes request logs older than the retention window', function () {
    ZgwRequestLog::create(['connection' => 'main', 'method' => 'GET', 'resource' => '/old', 'status_code' => 200, 'created_at' => now()->subDays(100)]);
    ZgwRequestLog::create(['connection' => 'main', 'method' => 'GET', 'resource' => '/recent', 'status_code' => 200, 'created_at' => now()->subDays(10)]);

    $this->artisan('zgw:prune-request-logs', ['--days' => 90])->assertSuccessful();

    expect(ZgwRequestLog::count())->toBe(1)
        ->and(ZgwRequestLog::first()->resource)->toBe('/recent');
});

it('prunes using the configured retention window when no --days is given', function () {
    config()->set('zgw.request_log_retention_days', 30);

    ZgwRequestLog::create(['connection' => 'main', 'method' => 'GET', 'resource' => '/old', 'status_code' => 200, 'created_at' => now()->subDays(40)]);
    ZgwRequestLog::create(['connection' => 'main', 'method' => 'GET', 'resource' => '/recent', 'status_code' => 200, 'created_at' => now()->subDays(10)]);

    $this->artisan('zgw:prune-request-logs')->assertSuccessful();

    expect(ZgwRequestLog::count())->toBe(1)
        ->and(ZgwRequestLog::first()->resource)->toBe('/recent');
});
