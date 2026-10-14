<?php

use App\Services\Notificaties\NotificatiesApi;
use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Exceptions\DisallowedHostException;

beforeEach(function () {
    config()->set('zgw.connections.main.urls.notificaties', 'https://nc.example.com/api/v1/');
});

test('patchAbonnement refuses an abonnement url on an unlisted host', function () {
    Http::fake();

    $api = new NotificatiesApi('main');

    expect(fn () => $api->patchAbonnement('https://evil.example.com/abonnement/abc', ['auth' => 'Bearer x']))
        ->toThrow(DisallowedHostException::class);

    // The connection's bearer token is never sent to the disallowed host.
    Http::assertNothingSent();
});

test('show refuses an abonnement url on an unlisted host', function () {
    Http::fake();

    $api = new NotificatiesApi('main');

    expect(fn () => $api->show('https://evil.example.com/abonnement/abc'))
        ->toThrow(DisallowedHostException::class);

    Http::assertNothingSent();
});

test('deleteAbonnement refuses an abonnement url on an unlisted host', function () {
    Http::fake();

    $api = new NotificatiesApi('main');

    expect(fn () => $api->deleteAbonnement('https://evil.example.com/abonnement/abc'))
        ->toThrow(DisallowedHostException::class);

    Http::assertNothingSent();
});

test('patchAbonnement allows an abonnement url on the configured host', function () {
    Http::fake([
        'https://nc.example.com/api/v1/abonnement/*' => Http::response(['url' => 'https://nc.example.com/api/v1/abonnement/abc'], 200),
    ]);

    $api = new NotificatiesApi('main');

    $api->patchAbonnement('https://nc.example.com/api/v1/abonnement/abc', ['auth' => 'Bearer x']);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request->url() === 'https://nc.example.com/api/v1/abonnement/abc');
});
