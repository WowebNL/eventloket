<?php

use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

function fakeIssuedJwt(): string
{
    $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

    return $segment(['typ' => 'JWT', 'alg' => 'none']).'.'.$segment(['jti' => 'tok-scope']).'.sig';
}

beforeEach(function () {
    // The webhook validates the hoofdObject host against the configured
    // connections.
    Config::set('openzaak.url', 'https://zgw.example.com/');

    Http::fake([
        rtrim((string) config('app.url'), '/').'/oauth/token' => Http::response([
            'access_token' => fakeIssuedJwt(),
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
        ], 200),
    ]);
});

it('mints the webhook client with the receive scope and nothing else', function () {
    // The client is minted for this one scope, so its credential reaches the
    // webhook and nothing else.
    $issued = app(WebhookTokenIssuer::class)->issue();

    $client = Client::findOrFail($issued->clientId);

    expect($client->scopes)->toBe([WebhookTokenIssuer::SCOPE])
        ->and($client->hasScope(WebhookTokenIssuer::SCOPE))->toBeTrue()
        ->and($client->hasScope('zaken.verwijderen'))->toBeFalse();
});

it('asks the token endpoint for that scope', function () {
    app(WebhookTokenIssuer::class)->issue();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/oauth/token')
        && ($request->data()['scope'] ?? null) === WebhookTokenIssuer::SCOPE);
});

it('does not let a client scoped for something else reach the webhook', function () {
    // The scope list is what the column exists for. Passport's
    // ScopeRepository::finalizeScopes() drops a scope the client does not carry,
    // so the token endpoint still answers but the token comes back without it,
    // and the webhook refuses it. Asserted end to end, because that refusal is
    // the behaviour that matters.
    $client = Client::factory()->asClientCredentials()->create([
        'secret' => '12345678',
        'scopes' => ['api:access'],
    ]);

    $token = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
        'scope' => WebhookTokenIssuer::SCOPE,
    ])->json('access_token');

    $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'destroy',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://zgw.example.com/zaken/api/v1/zaken/1',
        'resourceUrl' => 'https://zgw.example.com/zaken/api/v1/zaken/1',
        'aanmaakdatum' => now()->toIso8601String(),
    ], ['Authorization' => 'Bearer '.$token])->assertStatus(403);
});

it('does let the webhook client itself through', function () {
    $client = Client::factory()->asClientCredentials()->create([
        'secret' => '12345678',
        'scopes' => [WebhookTokenIssuer::SCOPE],
    ]);

    $token = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
        'scope' => WebhookTokenIssuer::SCOPE,
    ])->json('access_token');

    $this->postJson(route('api.open-notifications.listen'), [
        'actie' => 'destroy',
        'kanaal' => 'zaken',
        'resource' => 'zaak',
        'hoofdObject' => 'https://zgw.example.com/zaken/api/v1/zaken/1',
        'resourceUrl' => 'https://zgw.example.com/zaken/api/v1/zaken/1',
        'aanmaakdatum' => now()->toIso8601String(),
    ], ['Authorization' => 'Bearer '.$token])->assertStatus(200);
});
