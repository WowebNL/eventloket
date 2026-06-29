<?php

declare(strict_types=1);

use App\Services\Notificaties\NotificationRoundTripProbe;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config()->set('zgw.connections.main.urls.notificaties', 'https://nc.example.com/api/v1/');
});

it('publishes a test notification and records the receipt when it returns', function () {
    // The fake stands in for the Notificaties API delivering the published test
    // notification straight back to our callback (which records the receipt).
    Http::fake([
        'https://nc.example.com/api/v1/notificaties' => function (Request $request) {
            NotificationRoundTripProbe::recordReceipt(data_get($request->data(), 'kenmerken.probe_id'));

            return Http::response([], 201);
        },
    ]);

    $probeId = app(NotificationRoundTripProbe::class)->start('main');

    expect($probeId)->not->toBe('')
        ->and(NotificationRoundTripProbe::hasReceived($probeId))->toBeTrue();
});

it('leaves no receipt when the notification does not return', function () {
    Http::fake(['https://nc.example.com/api/v1/notificaties' => Http::response([], 201)]);

    $probeId = app(NotificationRoundTripProbe::class)->start('main');

    expect(NotificationRoundTripProbe::hasReceived($probeId))->toBeFalse();
});

it('throws when publishing the test notification fails', function () {
    Http::fake(['https://nc.example.com/api/v1/notificaties' => Http::response(['detail' => 'no test channel'], 500)]);

    app(NotificationRoundTripProbe::class)->start('main');
})->throws(RequestException::class);

it('ignores a receipt for an unknown probe id', function () {
    expect(NotificationRoundTripProbe::recordReceipt('unknown'))->toBeFalse()
        ->and(NotificationRoundTripProbe::hasReceived('unknown'))->toBeFalse();
});
