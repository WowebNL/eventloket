<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\Application;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use RuntimeException;
use Throwable;

/**
 * Issues scoped Passport client-credentials tokens for the notificaties webhook.
 *
 * A fresh confidential client is created for each issuance and its plaintext
 * secret is used immediately to request a notifications:receive token, then
 * discarded: the JWT is self-contained and validated by signature, so the
 * secret never needs to be stored. The registration command and the auto-renew
 * job both go through here, so the rotation logic lives in one place.
 */
class WebhookTokenIssuer
{
    public const APPLICATION_NAME = 'open-notificaties';

    public const SCOPE = 'notifications:receive';

    public function issue(): IssuedWebhookToken
    {
        $application = Application::firstOrCreate(['name' => self::APPLICATION_NAME]);

        $secret = Str::random(40);

        /** @var Client $client */
        $client = Client::create([
            'owner_type' => Application::class,
            'owner_id' => $application->id,
            'name' => self::APPLICATION_NAME,
            'secret' => $secret,
            'grant_types' => ['client_credentials'],
            'redirect_uris' => [],
            'revoked' => false,
        ]);

        $response = Http::asForm()->post(rtrim((string) config('app.url'), '/').'/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $secret,
            'scope' => self::SCOPE,
        ]);

        if (! $response->successful()) {
            $client->forceFill(['revoked' => true])->save();

            throw new RuntimeException('Kon geen webhook-token aanvragen via /oauth/token: HTTP '.$response->status().' — '.$response->body());
        }

        $jwt = (string) $response->json('access_token');
        $expiresIn = $response->json('expires_in');

        return new IssuedWebhookToken(
            token: $jwt,
            tokenId: $this->parseTokenId($jwt),
            clientId: (string) $client->getKey(),
            expiresAt: is_numeric($expiresIn) ? CarbonImmutable::now()->addSeconds((int) $expiresIn) : null,
        );
    }

    /**
     * Retire a previously issued webhook credential: revoke its access token and
     * delete the one-off client that minted it, so confidential clients do not
     * accumulate across rotations. A fresh client is created per issuance, so the
     * client is safe to delete once its token is no longer in use.
     *
     * Cleanup must never break the rotation that triggered it, so both steps are
     * best-effort and any failure is logged rather than thrown.
     */
    public function revoke(?string $tokenId, ?string $clientId): void
    {
        if ($tokenId !== null && $tokenId !== '') {
            try {
                Token::find($tokenId)?->revoke();
            } catch (Throwable $e) {
                Log::warning('Could not revoke a previous webhook token.', ['exception' => $e->getMessage()]);
            }
        }

        if ($clientId !== null && $clientId !== '') {
            try {
                Client::find($clientId)?->delete();
            } catch (Throwable $e) {
                Log::warning('Could not delete a previous webhook client.', ['exception' => $e->getMessage()]);
            }
        }
    }

    /**
     * Extract the token id (jti claim) from a Passport access token JWT.
     */
    private function parseTokenId(string $jwt): ?string
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        $jti = is_array($payload) ? ($payload['jti'] ?? null) : null;

        return is_string($jti) && $jti !== '' ? $jti : null;
    }
}
