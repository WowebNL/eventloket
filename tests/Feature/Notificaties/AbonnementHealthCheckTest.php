<?php

declare(strict_types=1);

use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementCheckStatus;
use App\Services\Notificaties\AbonnementHealthCheck;
use App\Services\Notificaties\AbonnementRegistrar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const ABO_URL = 'https://nc.example.com/api/v1/abonnement/abc';

beforeEach(function () {
    Cache::flush();
    config()->set('zgw.connections.main.urls.notificaties', 'https://nc.example.com/api/v1/');
});

function fakeRemoteAbonnement(array $kanalen): void
{
    Http::fake([
        ABO_URL => Http::response([
            'url' => ABO_URL,
            'kanalen' => collect($kanalen)->map(fn (string $naam): array => ['naam' => $naam, 'filters' => []])->all(),
        ], 200),
    ]);
}

function abonnement(array $attributes = []): ZgwAbonnement
{
    return ZgwAbonnement::factory()->create(array_merge([
        'connection' => 'main',
        'municipality_id' => null,
        'abonnement_url' => ABO_URL,
        'expires_at' => now()->addYear(),
    ], $attributes));
}

it('reports a healthy abonnement', function () {
    abonnement();
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::Healthy);
});

it('reports a missing notificaties url', function () {
    config()->set('zgw.connections.main.urls.notificaties', '');

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::NoNotificatiesUrl);
});

it('reports no local record', function () {
    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::NoLocalRecord);
});

it('reports a remote abonnement that no longer exists', function () {
    abonnement();
    Http::fake([ABO_URL => Http::response(['detail' => 'not found'], 404)]);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::RemoteMissing);
});

it('reports a kanalen mismatch and lists the missing channels', function () {
    abonnement();
    fakeRemoteAbonnement(['zaken', 'besluiten', 'documenten']); // missing 'zaaktypen'

    $result = app(AbonnementHealthCheck::class)->run('main');

    expect($result->status)->toBe(AbonnementCheckStatus::KanalenMismatch)
        ->and($result->missingKanalen)->toBe(['zaaktypen']);
});

it('reports an expired token', function () {
    abonnement(['expires_at' => now()->subDay()]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::TokenExpired);
});

it('reports a token expiring soon', function () {
    abonnement(['expires_at' => now()->addDays(3)]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::TokenExpiringSoon);
});
