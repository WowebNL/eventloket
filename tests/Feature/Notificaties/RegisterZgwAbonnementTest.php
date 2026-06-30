<?php

use App\Models\Application;
use App\Models\ZgwAbonnement;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

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
        ->and($abonnement->client_id)->not->toBeNull()
        ->and($abonnement->municipality_id)->toBeNull()
        ->and($abonnement->expires_at)->not->toBeNull()
        ->and($abonnement->last_renewed_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://nc.example.com/api/v1/abonnement'
        && $request->method() === 'POST'
        && $request['auth'] === 'Bearer '.$this->jwt
        && collect($request['kanalen'])->pluck('naam')->contains('zaken'));
});

test('retires the previous client when re-registering an existing abonnement', function () {
    $application = Application::firstOrCreate(['name' => WebhookTokenIssuer::APPLICATION_NAME]);
    $previousClient = Client::create([
        'owner_type' => Application::class,
        'owner_id' => $application->id,
        'name' => WebhookTokenIssuer::APPLICATION_NAME,
        'secret' => Str::random(40),
        'grant_types' => ['client_credentials'],
        'redirect_uris' => [],
        'revoked' => false,
    ]);

    ZgwAbonnement::factory()->create([
        'connection' => 'main',
        'abonnement_url' => 'https://nc.example.com/api/v1/abonnement/abc',
        'token_id' => 'old-token',
        'client_id' => $previousClient->getKey(),
    ]);

    $this->artisan('app:register-zgw-abonnementen --connection=main')->assertSuccessful();

    $abonnement = ZgwAbonnement::where('connection', 'main')->first();

    expect(Client::find($previousClient->getKey()))->toBeNull()
        ->and((string) $abonnement->client_id)->not->toBe((string) $previousClient->getKey())
        ->and(Client::where('name', WebhookTokenIssuer::APPLICATION_NAME)->count())->toBe(1);
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
