<?php

declare(strict_types=1);

namespace App\Jobs\Notificaties;

use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\NotificatiesApi;
use App\Services\Notificaties\StaticWebhookTokenIssuer;
use App\Services\Notificaties\WebhookAuthType;
use App\Services\Notificaties\WebhookCredentials;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rotates the webhook token of every ZGW abonnement whose token is close to
 * expiring: issues a fresh token of the type the abonnement was registered
 * with, PATCHes the abonnement's `auth` field (with its `kanalen`, see
 * {@see AbonnementRegistrar::kanalenPayload()}) via the Notificaties API,
 * updates storage and retires the previous token.
 *
 * An abonnement whose connection is now set to another credential type (or to
 * an unknown one) is not renewed: changing the type takes effect by registering
 * again, and silently extending the old type would hide the change. The job
 * logs a warning and the health check reports the mismatch.
 *
 * Each abonnement is renewed independently; a failure on one is logged and
 * reported but does not abort the rest, so the scheduled run is safe to retry.
 */
class RenewZgwAbonnementen implements ShouldQueue
{
    use Queueable;

    /**
     * Renew this many days before the token actually expires.
     */
    public const RENEW_WINDOW_DAYS = 14;

    public function handle(WebhookTokenIssuer $issuer, AbonnementRegistrar $registrar): void
    {
        $credentials = new WebhookCredentials($issuer, app(StaticWebhookTokenIssuer::class));
        $threshold = now()->addDays(self::RENEW_WINDOW_DAYS);

        ZgwAbonnement::query()
            ->expiringBefore($threshold)
            ->get()
            ->each(function (ZgwAbonnement $abonnement) use ($credentials, $registrar): void {
                try {
                    if (! $this->ownsAbonnement($abonnement, $registrar)) {
                        Log::warning('ZGW abonnement niet vernieuwd: deze connectie is niet de eigenaar van het abonnement op haar Notificaties API. Verwijder de dubbele rij; de verbindingstest van de eigenaar meldt welke.', [
                            'connection' => $abonnement->connection,
                        ]);

                        return;
                    }

                    if (! $this->typeStillConfigured($abonnement)) {
                        return;
                    }

                    $credentials->withLock(
                        $abonnement->connection,
                        fn () => $this->renew($abonnement->fresh() ?? $abonnement, $credentials),
                    );
                } catch (Throwable $exception) {
                    Log::error('ZGW abonnement vernieuwen mislukt.', [
                        'connection' => $abonnement->connection,
                        'abonnement_url' => $abonnement->abonnement_url,
                        'exception' => $exception->getMessage(),
                    ]);

                    // A rotation that cannot complete is not self-healing: the
                    // current token keeps working until it lapses, and then every
                    // delivery is refused. The health check deliberately stays
                    // quiet inside the renewal window because this job is meant to
                    // act there, so a failure here has to be loud on its own.
                    report($exception);
                }
            });
    }

    /**
     * Whether this row may rotate the token on its stored abonnement. A row
     * whose connection is refused on its Notificaties API (another connection
     * owns the abonnement there), or that was registered on another instance
     * than the one it is configured for now, would otherwise put its token on an
     * abonnement that is not its own, and the attribution of every notification
     * on it would flip at each renewal.
     */
    private function ownsAbonnement(ZgwAbonnement $abonnement, AbonnementRegistrar $registrar): bool
    {
        $baseUrl = (new NotificatiesApi($abonnement->connection))->baseUrl();

        return $registrar->sharedInstance($abonnement->connection, $baseUrl) === null
            && AbonnementRegistrar::registeredOnInstance($abonnement, $baseUrl);
    }

    /**
     * Whether the connection is still set to the type this abonnement was
     * registered with. Logs why not.
     */
    private function typeStillConfigured(ZgwAbonnement $abonnement): bool
    {
        $configured = WebhookAuthType::configuredFor($abonnement->connection);
        $registered = WebhookAuthType::ofAbonnement($abonnement);

        if ($configured === $registered) {
            return true;
        }

        Log::warning('ZGW abonnement niet vernieuwd: de ingestelde webhook_auth wijkt af van het type waarmee het abonnement is geregistreerd. Registreer het abonnement opnieuw.', [
            'connection' => $abonnement->connection,
            'registered' => $registered->value,
            'configured' => $configured->value ?? 'unknown',
        ]);

        return false;
    }

    private function renew(ZgwAbonnement $abonnement, WebhookCredentials $credentials): void
    {
        $previousTokenId = $abonnement->token_id;
        $previousClientId = $abonnement->client_id;

        // Valid before the PATCH goes out, because the Notificaties API calls our
        // webhook with the new token while it handles the PATCH.
        $token = $credentials->issue($abonnement->connection, WebhookAuthType::ofAbonnement($abonnement));

        try {
            (new NotificatiesApi($abonnement->connection))
                ->patchAbonnement((string) $abonnement->abonnement_url, [
                    'auth' => 'Bearer '.$token->token,
                    // The update replaces the abonnement's channels, so they are
                    // sent along with the new token to keep them in place.
                    'kanalen' => AbonnementRegistrar::kanalenPayload(),
                ]);
        } catch (Throwable $e) {
            // The patch failed, so the previous token still works. Retire the
            // credential we just minted so it does not leak, and let the caller
            // log and move on.
            $credentials->discard($token);

            throw WebhookCredentials::redact($e, $token);
        }

        $credentials->activate($token);

        $abonnement->update([
            'token_id' => $token->tokenId,
            'client_id' => $token->clientId,
            'expires_at' => $token->expiresAt,
            'last_renewed_at' => now(),
        ]);

        // The new token is registered, so the previous credential can be retired.
        $credentials->retirePrevious($abonnement->connection, $previousTokenId, $previousClientId, $token);

        Log::info('ZGW abonnement vernieuwd.', [
            'connection' => $abonnement->connection,
            'abonnement_url' => $abonnement->abonnement_url,
            'expires_at' => $token->expiresAt?->toIso8601String(),
        ]);
    }
}
