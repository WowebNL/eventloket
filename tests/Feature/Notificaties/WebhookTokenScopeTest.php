<?php

use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

function fakeIssuedJwt(): string
{
    $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

    return $segment(['typ' => 'JWT', 'alg' => 'none']).'.'.$segment(['jti' => 'tok-scope']).'.sig';
}

beforeEach(function () {
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
