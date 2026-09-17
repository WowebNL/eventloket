<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\ZgwAbonnement;
use Laravel\Passport\Token;
use Throwable;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

/**
 * Verifies that a connection's Open Notificaties abonnement is correctly
 * registered: a notificaties URL is configured, a local row exists, the remote
 * abonnement is still present, it subscribes to every expected channel, the
 * credential it presents on our webhook still satisfies that route, and the
 * webhook token has not (nearly) lapsed.
 */
class AbonnementHealthCheck
{
    /**
     * Renew abonnementen this many days before their token actually expires;
     * the check warns inside the same window the renewal job acts on.
     */
    private const RENEWAL_WINDOW_DAYS = 14;

    public function __construct(private readonly AbonnementRegistrar $registrar) {}

    public function run(string $connectionName): AbonnementCheckResult
    {
        $api = new NotificatiesApi($connectionName);

        try {
            $api->baseUrl();
        } catch (InvalidConfigurationException) {
            return new AbonnementCheckResult(AbonnementCheckStatus::NoNotificatiesUrl);
        }

        $local = ZgwAbonnement::query()->where('connection', $connectionName)->first();

        if ($local === null) {
            return new AbonnementCheckResult(AbonnementCheckStatus::NoLocalRecord);
        }

        $remote = $this->resolveRemote($api, $local);

        if ($remote === null) {
            return new AbonnementCheckResult(AbonnementCheckStatus::RemoteMissing);
        }

        $missingKanalen = $this->missingKanalen($remote);

        if ($missingKanalen !== []) {
            return new AbonnementCheckResult(AbonnementCheckStatus::KanalenMismatch, missingKanalen: $missingKanalen);
        }

        if ($local->expires_at !== null) {
            if ($local->expires_at->isPast()) {
                return new AbonnementCheckResult(AbonnementCheckStatus::TokenExpired, expiresAt: $local->expires_at);
            }

            if ($local->expires_at->lte(now()->addDays(self::RENEWAL_WINDOW_DAYS))) {
                return new AbonnementCheckResult(AbonnementCheckStatus::TokenExpiringSoon, expiresAt: $local->expires_at);
            }
        }

        // Judged last, and only outside the renewal window, because a token the
        // renewal job is about to replace is going to be replaced by a correctly
        // scoped one anyway. What does not heal itself is a long-lived token that
        // was minted before the webhook asked for the scope it now requires.
        if ($this->tokenCannotPassTheWebhook($local)) {
            return new AbonnementCheckResult(AbonnementCheckStatus::TokenNotUsable, expiresAt: $local->expires_at);
        }

        return new AbonnementCheckResult(AbonnementCheckStatus::Healthy, expiresAt: $local->expires_at);
    }

    /**
     * Whether the credential stored for this abonnement would be turned away by
     * our own webhook route, because it no longer exists, has been revoked, or
     * does not carry the scope that route requires.
     *
     * This is the one branch that reads the requirement rather than waiting for
     * refused deliveries: a token minted under an earlier contract keeps working
     * until the route asks for something it was never granted, and by then the
     * only symptom is a refusal that nobody is watching. The route and the issuer
     * share one constant, so the requirement cannot drift again, but a credential
     * issued before that requirement existed still can.
     *
     * A record without a stored token id predates token tracking and cannot be
     * judged either way, so it is left alone instead of reported as a fault.
     */
    private function tokenCannotPassTheWebhook(ZgwAbonnement $local): bool
    {
        if (! is_string($local->token_id) || $local->token_id === '') {
            return false;
        }

        $token = Token::find($local->token_id);

        if (! $token instanceof Token) {
            return true;
        }

        return (bool) $token->revoked || $token->cant(WebhookTokenIssuer::SCOPE);
    }

    /**
     * Read the remote abonnement back, preferring a direct GET on the stored url
     * and falling back to a callback-url match in the list. Any read failure (a
     * 404, an unreachable host) means it is effectively missing.
     *
     * @return array<string, mixed>|null
     */
    private function resolveRemote(NotificatiesApi $api, ZgwAbonnement $local): ?array
    {
        if (is_string($local->abonnement_url) && $local->abonnement_url !== '') {
            try {
                $remote = $api->show($local->abonnement_url);

                return $remote === [] ? null : $remote;
            } catch (Throwable) {
                return null;
            }
        }

        try {
            return $this->registrar->findRemote($api, $local->callback_url);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Expected channels not present in the remote abonnement.
     *
     * @param  array<string, mixed>  $remote
     * @return list<string>
     */
    private function missingKanalen(array $remote): array
    {
        $remoteKanalen = collect(is_array($remote['kanalen'] ?? null) ? $remote['kanalen'] : [])
            ->map(fn (mixed $kanaal): mixed => is_array($kanaal) ? ($kanaal['naam'] ?? null) : null)
            ->filter(fn (mixed $naam): bool => is_string($naam) && $naam !== '')
            ->values()
            ->all();

        return array_values(array_diff(AbonnementRegistrar::KANALEN, $remoteKanalen));
    }
}
