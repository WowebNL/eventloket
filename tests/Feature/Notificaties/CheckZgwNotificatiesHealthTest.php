<?php

declare(strict_types=1);

use App\Exceptions\NotificatiesHealthException;
use App\Models\ZgwAbonnement;
use App\Models\ZgwWebhookIngressState;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\WebhookIngressHealthCheck;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Token;

const HEALTHCHECK_ABO_URL = 'https://nc.example.com/api/v1/abonnement/abc';

beforeEach(function () {
    Exceptions::fake();
    config()->set('zgw.connections.main.urls.notificaties', 'https://nc.example.com/api/v1/');
});

function fakeNotificatiesRemote(): void
{
    Http::fake([
        HEALTHCHECK_ABO_URL => Http::response([
            'url' => HEALTHCHECK_ABO_URL,
            'kanalen' => collect(AbonnementRegistrar::KANALEN)
                ->map(fn (string $naam): array => ['naam' => $naam, 'filters' => []])
                ->all(),
        ], 200),
    ]);
}

function registeredAbonnement(array $attributes = []): ZgwAbonnement
{
    $token = Token::create([
        'id' => (string) Str::uuid(),
        'user_id' => null,
        'client_id' => (string) Str::uuid(),
        'name' => WebhookTokenIssuer::APPLICATION_NAME,
        'scopes' => [WebhookTokenIssuer::SCOPE],
        'revoked' => false,
        'expires_at' => now()->addYear(),
    ]);

    return ZgwAbonnement::factory()->create(array_merge([
        'connection' => 'main',
        'municipality_id' => null,
        'abonnement_url' => HEALTHCHECK_ABO_URL,
        'token_id' => $token->getKey(),
        'expires_at' => now()->addYear(),
    ], $attributes));
}

it('reports nothing when the koppeling is in order', function () {
    registeredAbonnement();
    fakeNotificatiesRemote();

    $this->artisan('app:check-zgw-notificaties')->assertSuccessful();

    Exceptions::assertNothingReported();
});

it('reports the shared connection when no abonnement has ever been registered', function () {
    // Nothing was ever registered for "main", so nothing ever arrives and nothing
    // is ever refused. Without an explicit check this state raises no signal at
    // all, which is the whole reason the command exists.
    $this->artisan('app:check-zgw-notificaties')->assertFailed();

    Exceptions::assertReported(NotificatiesHealthException::class);
});

it('reports a connection whose notificaties url is not configured', function () {
    config()->set('zgw.connections.main.urls.notificaties', '');

    $this->artisan('app:check-zgw-notificaties')->assertFailed();

    Exceptions::assertReported(
        fn (NotificatiesHealthException $e): bool => str_contains($e->getMessage(), 'no_notificaties_url'),
    );
});

it('reports a credential that cannot pass the webhook route', function () {
    $token = Token::create([
        'id' => (string) Str::uuid(),
        'user_id' => null,
        'client_id' => (string) Str::uuid(),
        'name' => WebhookTokenIssuer::APPLICATION_NAME,
        'scopes' => ['api:access'],
        'revoked' => false,
        'expires_at' => now()->addYear(),
    ]);

    registeredAbonnement(['token_id' => $token->getKey()]);
    fakeNotificatiesRemote();

    $this->artisan('app:check-zgw-notificaties')->assertFailed();

    Exceptions::assertReported(
        fn (NotificatiesHealthException $e): bool => str_contains($e->getMessage(), 'token_not_usable'),
    );
});

it('stays silent about a token inside its renewal window', function () {
    // The renewal job rotates this by itself, so reporting it would be noise, and
    // noise is what taught everyone to ignore the previous signal.
    registeredAbonnement(['expires_at' => now()->addDays(3)]);
    fakeNotificatiesRemote();

    $this->artisan('app:check-zgw-notificaties')->assertSuccessful();

    Exceptions::assertNothingReported();
});

it('reports a webhook that has been refusing deliveries', function () {
    registeredAbonnement();
    fakeNotificatiesRemote();

    $state = new ZgwWebhookIngressState;
    $state->forceFill([
        'id' => ZgwWebhookIngressState::SINGLETON_ID,
        'consecutive_rejections' => WebhookIngressHealthCheck::REJECTION_THRESHOLD,
        'rejection_streak_started_at' => now()->subDays(8),
    ])->save();

    $this->artisan('app:check-zgw-notificaties')->assertFailed();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(
        fn (NotificatiesHealthException $e): bool => str_contains($e->getMessage(), 'aanhoudend af'),
    );
});

it('reports a registered abonnement that has stopped delivering', function () {
    registeredAbonnement();
    fakeNotificatiesRemote();

    $state = new ZgwWebhookIngressState;
    $state->forceFill([
        'id' => ZgwWebhookIngressState::SINGLETON_ID,
        'last_accepted_at' => now()->subHours(WebhookIngressHealthCheck::SILENCE_HOURS + 1),
    ])->save();

    $this->artisan('app:check-zgw-notificaties')->assertFailed();

    Exceptions::assertReportedCount(1);
});

it('reports a remote abonnement that can no longer be read', function () {
    registeredAbonnement();
    Http::fake([HEALTHCHECK_ABO_URL => Http::response(['detail' => 'not found'], 404)]);

    $this->artisan('app:check-zgw-notificaties')->assertFailed();

    Exceptions::assertReported(
        fn (NotificatiesHealthException $e): bool => str_contains($e->getMessage(), 'remote_missing'),
    );
});
