<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\ZgwAbonnement;
use App\Models\ZgwWebhookToken;
use Laravel\Passport\Token;
use Throwable;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

/**
 * Verifies that a connection's Open Notificaties abonnement is correctly
 * registered: a notificaties URL is configured, no other connection holds the
 * abonnement on that Notificaties API, a local row exists that shares its
 * abonnement with no other connection, the remote abonnement is still present
 * and carries this connection's own webhook token, it subscribes to every
 * expected channel, the credential it presents on our webhook still satisfies
 * that route, the webhook token has not (nearly) lapsed, and the connection is
 * still set to the credential type the abonnement was registered with.
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

        if ($local->expires_at !== null && $local->expires_at->isPast()) {
            return new AbonnementCheckResult(AbonnementCheckStatus::TokenExpired, expiresAt: $local->expires_at);
        }

        // A changed setting only takes effect on the next registration, and the
        // renewal job leaves such an abonnement alone, so it has to be reported.
        if (WebhookAuthType::configuredFor($connectionName) !== WebhookAuthType::ofAbonnement($local)) {
            return new AbonnementCheckResult(AbonnementCheckStatus::WebhookAuthMismatch, expiresAt: $local->expires_at);
        }

        if ($local->expires_at !== null) {
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
     * our own webhook route, because it no longer exists, has been revoked, has
     * expired, or does not carry the scope that route requires.
     *
     * Expiry is read from the token itself, not only from the abonnement's copy
     * of it. That copy can be empty (the issuer leaves it unset when the grant
     * response carries no lifetime) and can drift from the token (a restored
     * backup, a rotation that only half completed), and the route judges the
     * token, not the copy.
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
     *
     * A static abonnement is judged on its own token rows: it needs a live one
     * with a stored hash, and an abonnement without an expiry never passes,
     * because the renewal job skips rows without one and the token would then
     * never be rotated.
     */
    private function tokenCannotPassTheWebhook(ZgwAbonnement $local): bool
    {
        if (WebhookAuthType::ofAbonnement($local) === WebhookAuthType::Static) {
            return $local->expires_at === null || self::currentStaticToken($local->connection) === null;
        }

        if (! is_string($local->token_id) || $local->token_id === '') {
            return false;
        }

        $token = Token::find($local->token_id);

        if (! $token instanceof Token) {
            return true;
        }

        return (bool) $token->revoked
            || ($token->expires_at?->isPast() ?? false)
            || $token->cant(WebhookTokenIssuer::SCOPE);
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

        // Decided before the JWT reading below, which a static token would not
        // survive. Anything but one of this connection's own static tokens is
        // foreign here.
        if (WebhookAuthType::ofAbonnement($local) === WebhookAuthType::Static) {
            if (! is_string($auth)) {
                return false;
            }

            $lookup = StaticWebhookTokenIssuer::lookupOf((string) preg_replace('/^Bearer\s+/i', '', trim($auth)));

            return $lookup === null || ! ZgwWebhookToken::query()
                ->where('connection', $local->connection)
                ->where('lookup', $lookup)
                ->whereNull('revoked_at')
                ->exists();
        }

        if (! is_string($auth) || $local->token_id === null || $local->token_id === '') {
            return false;
        }

        $tokenId = WebhookTokenIssuer::tokenIdFromJwt((string) preg_replace('/^Bearer\s+/i', '', trim($auth)));

        return $tokenId !== null && $tokenId !== $local->token_id;
    }

    /**
     * The usable static token of a connection that lives longest, or null when
     * it has none.
     */
    private static function currentStaticToken(string $connectionName): ?ZgwWebhookToken
    {
        $token = ZgwWebhookToken::query()
            ->where('connection', $connectionName)
            ->whereNull('revoked_at')
            ->whereNotNull('secret_hash')
            ->orderByDesc('expires_at')
            ->first();

        return $token instanceof ZgwWebhookToken && $token->isUsable() ? $token : null;
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
