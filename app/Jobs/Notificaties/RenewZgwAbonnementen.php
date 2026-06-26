<?php

declare(strict_types=1);

namespace App\Jobs\Notificaties;

use App\Models\ZgwAbonnement;
use App\Services\Notificaties\NotificatiesApi;
use App\Services\Notificaties\WebhookTokenIssuer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Token;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Renew this many days before the token actually expires.
     */
    public const RENEW_WINDOW_DAYS = 14;

    public function handle(WebhookTokenIssuer $issuer): void
    {
        $threshold = now()->addDays(self::RENEW_WINDOW_DAYS);

        ZgwAbonnement::query()
            ->expiringBefore($threshold)
            ->get()
            ->each(function (ZgwAbonnement $abonnement) use ($issuer): void {
                try {
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

    private function renew(ZgwAbonnement $abonnement, WebhookTokenIssuer $issuer): void
    {
        $token = $issuer->issue();

        (new NotificatiesApi($abonnement->connection))
            ->patchAbonnement((string) $abonnement->abonnement_url, ['auth' => 'Bearer '.$token->token]);

        $previousTokenId = $abonnement->token_id;

        $abonnement->update([
            'token_id' => $token->tokenId,
            'expires_at' => $token->expiresAt,
            'last_renewed_at' => now(),
        ]);

        $this->revokePreviousToken($previousTokenId);

        Log::info('ZGW abonnement vernieuwd.', [
            'connection' => $abonnement->connection,
            'abonnement_url' => $abonnement->abonnement_url,
            'expires_at' => $token->expiresAt?->toIso8601String(),
        ]);
    }

    private function revokePreviousToken(?string $tokenId): void
    {
        if ($tokenId === null || $tokenId === '') {
            return;
        }

        Token::find($tokenId)?->revoke();
    }
}
