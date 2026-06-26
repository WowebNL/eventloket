<?php

use App\Jobs\Notificaties\RenewZgwAbonnementen;
use App\Models\ZgwAbonnement;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function renewJwt(string $tokenId): string
{
    $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

    return $segment(['typ' => 'JWT', 'alg' => 'RS256']).'.'.$segment(['jti' => $tokenId]).'.signature';
}

/**
 * @return array<string, mixed>
 */
function renewStubs(string $jwt, int $patchStatus = 200): array
{
    return [
        rtrim((string) config('app.url'), '/').'/oauth/token' => Http::response([
            'access_token' => $jwt,
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
        ], 200),
        'https://nc.example.com/api/v1/abonnement/*' => Http::response(
            $patchStatus === 200 ? ['url' => 'https://nc.example.com/api/v1/abonnement/abc'] : ['detail' => 'boom'],
            $patchStatus,
        ),
    ];
}

beforeEach(function () {
    config()->set('zgw.connections.main.urls.notificaties', 'https://nc.example.com/api/v1/');
    $this->jwt = renewJwt('new-token');
});

test('renews an abonnement whose token expires within the window', function () {
    Http::fake(renewStubs($this->jwt));

    $abonnement = ZgwAbonnement::factory()->create([
        'connection' => 'main',
        'abonnement_url' => 'https://nc.example.com/api/v1/abonnement/abc',
        'token_id' => 'old-token',
        'expires_at' => now()->addDays(5),
        'last_renewed_at' => null,
    ]);

    dispatch_sync(new RenewZgwAbonnementen);

    $abonnement->refresh();

    expect($abonnement->token_id)->toBe('new-token')
        ->and($abonnement->last_renewed_at)->not->toBeNull()
        ->and($abonnement->expires_at->isAfter(now()->addMonths(6)))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://nc.example.com/api/v1/abonnement/abc'
        && $request->method() === 'PATCH'
        && $request['auth'] === 'Bearer '.$this->jwt);
});

test('leaves an abonnement whose token expires far in the future untouched', function () {
    Http::fake(renewStubs($this->jwt));

    ZgwAbonnement::factory()->create([
        'connection' => 'main',
        'abonnement_url' => 'https://nc.example.com/api/v1/abonnement/abc',
        'token_id' => 'old-token',
        'expires_at' => now()->addMonths(6),
    ]);

    dispatch_sync(new RenewZgwAbonnementen);

    expect(ZgwAbonnement::first()->token_id)->toBe('old-token');

    Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
});

test('a failing renewal is caught and leaves the stored token unchanged', function () {
    Http::fake(renewStubs($this->jwt, patchStatus: 500));

    ZgwAbonnement::factory()->create([
        'connection' => 'main',
        'abonnement_url' => 'https://nc.example.com/api/v1/abonnement/abc',
        'token_id' => 'old-token',
        'expires_at' => now()->addDays(5),
    ]);

    // Must not throw; the failure is logged per-abonnement.
    dispatch_sync(new RenewZgwAbonnementen);

    expect(ZgwAbonnement::first()->token_id)->toBe('old-token');
});
