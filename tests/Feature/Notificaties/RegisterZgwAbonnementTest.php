<?php

use App\Models\ZgwAbonnement;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakeWebhookJwt(string $tokenId): string
{
    $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

    return $segment(['typ' => 'JWT', 'alg' => 'RS256']).'.'.$segment(['jti' => $tokenId]).'.signature';
}

beforeEach(function () {
    config()->set('zgw.connections.main.urls.notificaties', 'https://nc.example.com/api/v1/');

    $this->jwt = fakeWebhookJwt('tok-123');

    Http::fake([
        rtrim((string) config('app.url'), '/').'/oauth/token' => Http::response([
            'access_token' => $this->jwt,
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
        ], 200),
        'https://nc.example.com/api/v1/abonnement' => function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['count' => 0, 'next' => null, 'previous' => null, 'results' => []], 200);
            }

            return Http::response(['url' => 'https://nc.example.com/api/v1/abonnement/abc'], 201);
        },
    ]);
});

test('registers an abonnement on the main connection and stores it', function () {
    $this->artisan('app:register-zgw-abonnementen --connection=main')->assertSuccessful();

    $abonnement = ZgwAbonnement::where('connection', 'main')->first();

    expect($abonnement)->not->toBeNull()
        ->and($abonnement->abonnement_url)->toBe('https://nc.example.com/api/v1/abonnement/abc')
        ->and($abonnement->token_id)->toBe('tok-123')
        ->and($abonnement->municipality_id)->toBeNull()
        ->and($abonnement->expires_at)->not->toBeNull()
        ->and($abonnement->last_renewed_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://nc.example.com/api/v1/abonnement'
        && $request->method() === 'POST'
        && $request['auth'] === 'Bearer '.$this->jwt
        && collect($request['kanalen'])->pluck('naam')->contains('zaken'));
});

test('skips a connection without a notificaties url', function () {
    config()->set('zgw.connections.main.urls.notificaties', '');

    $this->artisan('app:register-zgw-abonnementen --connection=main')->assertSuccessful();

    expect(ZgwAbonnement::count())->toBe(0);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/abonnement'));
});

test('does not contact the apis in dry-run mode', function () {
    $this->artisan('app:register-zgw-abonnementen --connection=main --dry-run')->assertSuccessful();

    expect(ZgwAbonnement::count())->toBe(0);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/abonnement')
        || str_contains($request->url(), '/oauth/token'));
});
