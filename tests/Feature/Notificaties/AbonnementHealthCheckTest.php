<?php

declare(strict_types=1);

use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementCheckStatus;
use App\Services\Notificaties\AbonnementHealthCheck;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Token;

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

/**
 * The Passport access token an abonnement stores: the credential the remote side
 * presents back on our webhook.
 */
function webhookToken(array $attributes = []): Token
{
    return Token::create(array_merge([
        'id' => (string) Str::uuid(),
        'user_id' => null,
        'client_id' => (string) Str::uuid(),
        'name' => WebhookTokenIssuer::APPLICATION_NAME,
        'scopes' => [WebhookTokenIssuer::SCOPE],
        'revoked' => false,
        'expires_at' => now()->addYear(),
    ], $attributes));
}

function abonnement(array $attributes = []): ZgwAbonnement
{
    $attributes = array_merge([
        'connection' => 'main',
        'municipality_id' => null,
        'abonnement_url' => ABO_URL,
        'expires_at' => now()->addYear(),
    ], $attributes);

    // A registered abonnement always points at a real token; the factory alone
    // only invents an id, which is exactly the state this check calls a fault.
    if (! array_key_exists('token_id', $attributes)) {
        $attributes['token_id'] = webhookToken()->getKey();
    }

    return ZgwAbonnement::factory()->create($attributes);
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

it('reports a token that does not carry the scope the webhook requires', function () {
    abonnement(['token_id' => webhookToken(['scopes' => ['api:access']])->getKey()]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::TokenNotUsable);
});

it('reports a token that no longer exists', function () {
    abonnement(['token_id' => (string) Str::uuid()]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::TokenNotUsable);
});

it('reports a revoked token', function () {
    abonnement(['token_id' => webhookToken(['revoked' => true])->getKey()]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::TokenNotUsable);
});

it('accepts a wildcard-scoped token', function () {
    abonnement(['token_id' => webhookToken(['scopes' => ['*']])->getKey()]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::Healthy);
});

it('leaves an abonnement without a stored token id alone', function () {
    abonnement(['token_id' => null]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::Healthy);
});

it('never calls an expired token healthy when the stored expiry still lies ahead', function () {
    // The abonnement's own copy of the expiry says a year to go, while the token
    // the remote presents lapsed yesterday. The route judges the token, so the
    // check has to as well.
    abonnement([
        'expires_at' => now()->addYear(),
        'token_id' => webhookToken(['expires_at' => now()->subDay()])->getKey(),
    ]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::TokenNotUsable);
});

it('never calls an expired token healthy when no expiry was stored', function () {
    abonnement([
        'expires_at' => null,
        'token_id' => webhookToken(['expires_at' => now()->subDay()])->getKey(),
    ]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::TokenNotUsable);
});

it('reports an expired token before it judges its scope', function () {
    abonnement([
        'expires_at' => now()->subDay(),
        'token_id' => webhookToken(['scopes' => ['api:access']])->getKey(),
    ]);
    fakeRemoteAbonnement(AbonnementRegistrar::KANALEN);

    expect(app(AbonnementHealthCheck::class)->run('main')->status)
        ->toBe(AbonnementCheckStatus::TokenExpired);
});
