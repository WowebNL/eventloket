<?php

declare(strict_types=1);

namespace App\Jobs\Notificaties;

use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\NotificatiesApi;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rotates the scoped webhook token of every ZGW abonnement whose token is close
 * to expiring: issues a fresh token, PATCHes the abonnement's `auth` field via
 * the Notificaties API, updates storage and revokes the previous token.
 *
 * Each abonnement is renewed independently; a failure on one is logged and does
 * not abort the rest, so the scheduled run is safe to retry.
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
        $threshold = now()->addDays(self::RENEW_WINDOW_DAYS);

        ZgwAbonnement::query()
            ->expiringBefore($threshold)
            ->get()
            ->each(function (ZgwAbonnement $abonnement) use ($issuer, $registrar): void {
                try {
                    if (! $this->ownsAbonnement($abonnement, $registrar)) {
                        Log::warning('ZGW abonnement niet vernieuwd: deze connectie is niet de eigenaar van het abonnement op haar Notificaties API. Verwijder de dubbele rij; de verbindingstest van de eigenaar meldt welke.', [
                            'connection' => $abonnement->connection,
                        ]);

                        return;
                    }

                    $this->renew($abonnement, $issuer);
                } catch (Throwable $exception) {
                    Log::error('ZGW abonnement vernieuwen mislukt.', [
                        'connection' => $abonnement->connection,
                        'abonnement_url' => $abonnement->abonnement_url,
                        'exception' => $exception->getMessage(),
                    ]);
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

    private function renew(ZgwAbonnement $abonnement, WebhookTokenIssuer $issuer): void
    {
        $previousTokenId = $abonnement->token_id;
        $previousClientId = $abonnement->client_id;

        $token = $issuer->issue();

        try {
            (new NotificatiesApi($abonnement->connection))
                ->patchAbonnement((string) $abonnement->abonnement_url, ['auth' => 'Bearer '.$token->token]);
        } catch (Throwable $e) {
            // The patch failed, so the previous token still works. Retire the
            // credential we just minted so it does not leak, and let the caller
            // log and move on.
            $issuer->revoke($token->tokenId, $token->clientId);

            throw $e;
        }

        $abonnement->update([
            'token_id' => $token->tokenId,
            'client_id' => $token->clientId,
            'expires_at' => $token->expiresAt,
            'last_renewed_at' => now(),
        ]);

        // The new token is registered, so the previous credential can be retired.
        $issuer->revoke($previousTokenId, $previousClientId);

        Log::info('ZGW abonnement vernieuwd.', [
            'connection' => $abonnement->connection,
            'abonnement_url' => $abonnement->abonnement_url,
            'expires_at' => $token->expiresAt?->toIso8601String(),
        ]);
    }
}
