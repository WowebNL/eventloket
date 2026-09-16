<?php

declare(strict_types=1);

use App\Models\MunicipalityZgwConnection;
use App\Models\ZgwAbonnement;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('zgw.connections.main.urls.notificaties', 'https://nc.example.com/api/v1/');
});

const LIST_ABO_URL = 'https://nc.example.com/api/v1/abonnement/abc';

function fakeAbonnementenList(): void
{
    Http::fake([
        'https://nc.example.com/api/v1/abonnement' => Http::response([
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [[
                'url' => LIST_ABO_URL,
                'callbackUrl' => 'https://eventloket.example.com/api/open-notifications/listen',
                'kanalen' => [['naam' => 'zaken'], ['naam' => 'documenten']],
            ]],
        ], 200),
        LIST_ABO_URL => Http::response([], 204),
    ]);
}

it('lists the abonnementen of a connection', function () {
    fakeAbonnementenList();

    $this->artisan('app:list-zgw-abonnementen --connection=main')
        ->assertSuccessful()
        ->expectsOutputToContain(LIST_ABO_URL);
});

it('deletes an abonnement and cleans up the local record', function () {
    fakeAbonnementenList();

    ZgwAbonnement::factory()->create(['connection' => 'main', 'abonnement_url' => LIST_ABO_URL]);

    $this->artisan('app:list-zgw-abonnementen', [
        '--connection' => 'main',
        '--delete' => LIST_ABO_URL,
        '--force' => true,
    ])->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->url() === LIST_ABO_URL && $request->method() === 'DELETE');

    expect(ZgwAbonnement::where('abonnement_url', LIST_ABO_URL)->exists())->toBeFalse();
});

it('registers the runtime config for an explicit gemeente connection', function () {
    // Regression: an explicit --connection=gemeente_X must register the
    // per-connection runtime config, otherwise it looks like it has no
    // notificaties URL even though it does.
    $connection = MunicipalityZgwConnection::factory()->active()->create();
    $aboUrl = 'https://gemeente.example.com/notificaties/api/v1/abonnement/xyz';

    Http::fake([
        'https://gemeente.example.com/notificaties/api/v1/abonnement' => Http::response([
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [['url' => $aboUrl, 'callbackUrl' => 'https://eventloket.example.com/api/open-notifications/listen', 'kanalen' => [['naam' => 'zaken']]]],
        ], 200),
    ]);

    $this->artisan("app:list-zgw-abonnementen --connection=gemeente_{$connection->municipality_id}")
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Geen notificaties-URL')
        ->expectsOutputToContain($aboUrl);
});

it('skips a connection without a notificaties url', function () {
    config()->set('zgw.connections.main.urls.notificaties', '');

    $this->artisan('app:list-zgw-abonnementen --connection=main')
        ->assertSuccessful()
        ->expectsOutputToContain('Geen notificaties-URL');
});
