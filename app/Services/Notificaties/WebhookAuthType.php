<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use App\Models\MunicipalityZgwConnection;
use App\Models\ZgwAbonnement;

/**
 * The kind of credential an abonnement carries in its `auth` field, which the
 * Notificaties API presents back on our webhook.
 *
 * Passport is a scoped Passport access token (a signed JWT). Static is a short
 * opaque token for a Notificaties API that cannot store a JWT of that length.
 */
enum WebhookAuthType: string
{
    case Passport = 'passport';
    case Static = 'static';

    /**
     * The configured setting of a connection, as written, without validation.
     *
     * The main connection reads its own config. A per-municipality connection
     * reads the column on its row directly, so the answer does not depend on
     * whether the connection's runtime config was registered in this request,
     * and an empty column means "passport" rather than main's value. This is
     * the one place the setting is read, so the issuer, the webhook and the
     * health check cannot disagree about it.
     */
    public static function configuredValue(string $connectionName): string
    {
        $municipalityId = ZgwAbonnement::municipalityIdFromConnection($connectionName);

        $value = $municipalityId === null
            ? config("zgw.connections.{$connectionName}.webhook_auth")
            : MunicipalityZgwConnection::query()->where('municipality_id', $municipalityId)->value('webhook_auth');

        return is_string($value) && $value !== '' ? $value : self::Passport->value;
    }

    /**
     * The configured setting of a connection, or null when it holds a value
     * that is not a known credential type. Callers refuse an unknown value
     * instead of falling back to a default, so a typo never silently lands on
     * the wrong mechanism.
     */
    public static function configuredFor(string $connectionName): ?self
    {
        return self::tryFrom(self::configuredValue($connectionName));
    }

    /**
     * The configured setting, or an exception naming the connection when the
     * value is unknown. Used where a credential is about to be issued.
     */
    public static function requireConfiguredFor(string $connectionName): self
    {
        $type = self::configuredFor($connectionName);

        if ($type === null) {
            throw new InvalidWebhookAuthException(sprintf(
                'Unknown webhook_auth value for ZGW connection "%s"; expected one of: %s.',
                $connectionName,
                implode(', ', array_map(fn (self $case): string => $case->value, self::cases())),
            ));
        }

        return $type;
    }

    /**
     * The type an abonnement row was registered with. A row that predates the
     * column, or holds a value this version does not know, counts as Passport,
     * which is what every abonnement carried before the static type existed.
     */
    public static function ofAbonnement(ZgwAbonnement $abonnement): self
    {
        return self::tryFrom((string) $abonnement->auth_type) ?? self::Passport;
    }
}
