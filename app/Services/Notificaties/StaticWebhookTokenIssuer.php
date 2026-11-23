<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\ZgwWebhookToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Issues, verifies and retires short opaque webhook tokens.
 *
 * A token reads "elw.<lookup>.<secret>": the lookup is 16 random bytes as hex
 * and finds the row through a unique index, the secret is 32 random bytes as
 * base64url. With "Bearer " in front it stays well under 100 characters, so it
 * fits a Notificaties API that limits the length of an abonnement's `auth`
 * field. Only the SHA-256 hash of the secret is stored. The secret carries 256
 * bits of randomness, so a salt or a slow hash would add nothing but latency.
 *
 * A new token becomes valid before the outgoing request that registers it,
 * because the Notificaties API calls our webhook with it while handling that
 * request. Until the request succeeds it only lives for a short pending window;
 * {@see activate()} then gives it its full lifetime.
 */
class StaticWebhookTokenIssuer
{
    public const PREFIX = 'elw';

    /**
     * The exact shape of a token. Anything else is refused before a query runs.
     */
    public const PATTERN = '/^elw\.([0-9a-f]{32})\.([A-Za-z0-9_-]{43})$/D';

    /**
     * How long a token that has not been confirmed by a successful registration
     * stays valid. Long enough to cover the outgoing request and the callback it
     * triggers, short enough that a registration that died halfway leaves
     * nothing usable behind for long.
     */
    public const PENDING_MINUTES = 10;

    public const MAX_LIFETIME_DAYS = 366;

    public const MAX_OVERLAP_MINUTES = 15;

    /**
     * Create a token for the connection, valid for the pending window only.
     */
    public function issue(string $connectionName): IssuedWebhookToken
    {
        $lookup = bin2hex(random_bytes(16));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $row = ZgwWebhookToken::query()->create([
            'connection' => $connectionName,
            'lookup' => $lookup,
            'secret_hash' => self::hash($secret),
            'expires_at' => now()->addMinutes(self::PENDING_MINUTES),
        ]);

        return new IssuedWebhookToken(
            token: self::PREFIX.'.'.$lookup.'.'.$secret,
            tokenId: null,
            clientId: null,
            expiresAt: CarbonImmutable::now()->addDays(self::lifetimeDays()),
            type: WebhookAuthType::Static,
            staticTokenId: $row->id,
        );
    }

    /**
     * Give a token whose registration succeeded its full lifetime.
     */
    public function activate(IssuedWebhookToken $token): void
    {
        if ($token->staticTokenId === null || $token->expiresAt === null) {
            return;
        }

        ZgwWebhookToken::query()
            ->whereKey($token->staticTokenId)
            ->whereNull('revoked_at')
            ->update(['expires_at' => $token->expiresAt]);
    }

    /**
     * Revoke a token whose registration failed.
     */
    public function discard(IssuedWebhookToken $token): void
    {
        if ($token->staticTokenId === null) {
            return;
        }

        self::revokeQuery(ZgwWebhookToken::query()->whereKey($token->staticTokenId));
    }

    /**
     * Retire every other live token of the connection. With an overlap they keep
     * working for the configured overlap (never longer than they already would),
     * so deliveries already in flight with the previous token still pass;
     * without one they are revoked at once.
     */
    public function retireOthers(string $connectionName, ?int $keepId, bool $withOverlap): void
    {
        $query = ZgwWebhookToken::query()
            ->where('connection', $connectionName)
            ->whereNull('revoked_at')
            ->when($keepId !== null, fn ($query) => $query->whereKeyNot($keepId));

        if (! $withOverlap) {
            self::revokeQuery($query);

            return;
        }

        $query->where('expires_at', '>', now()->addMinutes(self::overlapMinutes()))
            ->update(['expires_at' => now()->addMinutes(self::overlapMinutes())]);
    }

    /**
     * The row a presented bearer token authenticates as, or null when it does
     * not authenticate: a malformed value, an unknown lookup, a revoked or
     * expired token, or a secret that does not match.
     */
    public function verify(#[\SensitiveParameter] string $bearer): ?ZgwWebhookToken
    {
        if (preg_match(self::PATTERN, $bearer, $matches) !== 1) {
            return null;
        }

        // Only the lookup reaches the query; the secret is compared in PHP.
        $row = ZgwWebhookToken::query()->where('lookup', $matches[1])->first();

        if (! $row instanceof ZgwWebhookToken || ! $row->isUsable()) {
            return null;
        }

        return hash_equals((string) $row->secret_hash, self::hash($matches[2])) ? $row : null;
    }

    /**
     * Whether a bearer token has the shape of a static token. Says nothing about
     * whether it is genuine.
     */
    public static function looksLikeStaticToken(string $bearer): bool
    {
        return str_starts_with($bearer, self::PREFIX.'.');
    }

    /**
     * The lookup part of a well-formed static token, without checking the
     * secret. Used to recognise our own credential, never to authenticate.
     */
    public static function lookupOf(#[\SensitiveParameter] string $bearer): ?string
    {
        return preg_match(self::PATTERN, $bearer, $matches) === 1 ? $matches[1] : null;
    }

    public static function lifetimeDays(): int
    {
        $days = (int) config('zgw.webhook.static_token_lifetime_days', 365);

        return max(1, min(self::MAX_LIFETIME_DAYS, $days));
    }

    public static function overlapMinutes(): int
    {
        $minutes = (int) config('zgw.webhook.static_token_overlap_minutes', 5);

        return max(0, min(self::MAX_OVERLAP_MINUTES, $minutes));
    }

    private static function hash(#[\SensitiveParameter] string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * @param  Builder<ZgwWebhookToken>  $query
     */
    private static function revokeQuery($query): void
    {
        $query->update(['secret_hash' => null, 'revoked_at' => now()]);
    }
}
