<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\ZgwAbonnement;
use Throwable;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

/**
 * Verifies that a connection's Open Notificaties abonnement is correctly
 * registered: a notificaties URL is configured, no other connection holds the
 * abonnement on that Notificaties API, a local row exists that shares its
 * abonnement with no other connection, the remote abonnement is still present
 * and carries this connection's own webhook token, it subscribes to every
 * expected channel and the webhook token has not (nearly) lapsed.
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
            $baseUrl = $api->baseUrl();
        } catch (InvalidConfigurationException) {
            return new AbonnementCheckResult(AbonnementCheckStatus::NoNotificatiesUrl);
        }

        $shared = $this->registrar->sharedInstance($connectionName, $baseUrl);

        if ($shared !== null) {
            return new AbonnementCheckResult(
                $shared->inherited ? AbonnementCheckStatus::NotificatiesUrlInherited : AbonnementCheckStatus::NotificatiesInstanceInUse,
                otherConnection: $shared->owner,
            );
        }

        $local = ZgwAbonnement::query()->where('connection', $connectionName)->first();

        if ($local === null) {
            return new AbonnementCheckResult(AbonnementCheckStatus::NoLocalRecord);
        }

        $sharer = $this->connectionSharingAbonnement($local);

        if ($sharer !== null) {
            return new AbonnementCheckResult(AbonnementCheckStatus::SharedAbonnement, otherConnection: $sharer);
        }

        $remote = $this->resolveRemote($api, $baseUrl, $local);

        if ($remote === null) {
            return new AbonnementCheckResult(AbonnementCheckStatus::RemoteMissing);
        }

        if ($this->presentsForeignToken($remote, $local)) {
            return new AbonnementCheckResult(AbonnementCheckStatus::AuthMismatch);
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

        return new AbonnementCheckResult(AbonnementCheckStatus::Healthy, expiresAt: $local->expires_at);
    }

    /**
     * Read the remote abonnement back, preferring a direct GET on the stored url
     * and falling back to a callback-url match in the list. Any read failure (a
     * 404, an unreachable host) means it is effectively missing, and so does a
     * row registered on another instance than the configured one (the
     * notificaties URL changed since it was registered).
     *
     * @return array<string, mixed>|null
     */
    private function resolveRemote(NotificatiesApi $api, string $baseUrl, ZgwAbonnement $local): ?array
    {
        if (is_string($local->abonnement_url) && $local->abonnement_url !== '') {
            if (! AbonnementRegistrar::registeredOnInstance($local, $baseUrl)) {
                return null;
            }

            try {
                $remote = $api->show($local->abonnement_url);

                return $remote === [] ? null : $remote;
            } catch (Throwable) {
                return null;
            }
        }

        try {
            return $this->registrar->findRemote($api, $local->callback_url, $local->connection);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Another connection whose row stores the same abonnement URL. Both then
     * rotate the token on one abonnement, so it is attributed to whichever renewed
     * last.
     */
    private function connectionSharingAbonnement(ZgwAbonnement $local): ?string
    {
        if (! is_string($local->abonnement_url) || $local->abonnement_url === '') {
            return null;
        }

        $other = ZgwAbonnement::query()
            ->where('connection', '!=', $local->connection)
            ->where('abonnement_url', $local->abonnement_url)
            ->orderBy('id')
            ->value('connection');

        return is_string($other) ? $other : null;
    }

    /**
     * Whether the remote abonnement carries a webhook token other than the one
     * this connection issued. Only decidable when the Notificaties API returns the
     * auth value and it holds a token id; Open Notificaties treats the field as
     * write-only, in which case this check cannot fire and the shared-abonnement
     * check above is what catches a takeover.
     *
     * @param  array<string, mixed>  $remote
     */
    private function presentsForeignToken(array $remote, ZgwAbonnement $local): bool
    {
        $auth = $remote['auth'] ?? null;

        if (! is_string($auth) || $local->token_id === null || $local->token_id === '') {
            return false;
        }

        $tokenId = WebhookTokenIssuer::tokenIdOf((string) preg_replace('/^Bearer\s+/i', '', trim($auth)));

        return $tokenId !== null && $tokenId !== $local->token_id;
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
