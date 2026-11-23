<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

/**
 * The outcome of verifying that a connection's Open Notificaties abonnement is
 * correctly registered. Severity drives how the UI surfaces it.
 */
enum AbonnementCheckStatus
{
    case NoNotificatiesUrl;
    /** The notificaties URL is inherited from the main connection, whose abonnement lives there. */
    case NotificatiesUrlInherited;
    /** Another connection already holds the abonnement on this Notificaties API. */
    case NotificatiesInstanceInUse;
    case NoLocalRecord;
    /** Another connection has the same abonnement URL stored, so both claim one abonnement. */
    case SharedAbonnement;
    case RemoteMissing;
    /** The remote abonnement presents a webhook token that is not this connection's. */
    case AuthMismatch;
    case KanalenMismatch;
    case TokenExpired;

    /**
     * The credential the remote side presents on our webhook cannot pass that
     * route: it is gone, it has been revoked, or it does not carry the scope the
     * route requires. Distinct from {@see TokenExpired}, which the renewal job
     * resolves on its own; this one needs the abonnement registered again.
     */
    case TokenNotUsable;
    case TokenExpiringSoon;

    /**
     * The connection is set to another webhook credential type than the one the
     * abonnement was registered with, or to a value that is not a known type.
     * The registered credential keeps working, but the renewal job leaves the
     * abonnement alone until it is registered again.
     */
    case WebhookAuthMismatch;

    /**
     * {@see WebhookAuthMismatch} inside the renewal window: the renewal job
     * will not rotate the token, so deliveries stop at its expiry unless the
     * abonnement is registered again first.
     */
    case WebhookAuthMismatchExpiring;
    case Healthy;

    public function isOk(): bool
    {
        return $this === self::Healthy;
    }

    public function isWarning(): bool
    {
        return in_array($this, [
            self::NoNotificatiesUrl,
            self::NotificatiesUrlInherited,
            self::NotificatiesInstanceInUse,
            self::KanalenMismatch,
            self::TokenExpiringSoon,
            self::WebhookAuthMismatch,
        ], true);
    }

    /**
     * Whether this connection receives its notifications through the abonnement
     * another connection holds on the same Notificaties API. That is the
     * intended set-up of one abonnement per instance, not a fault: the check of
     * the connection that holds the abonnement watches the delivery.
     */
    public function isDeliveredThroughAnotherConnection(): bool
    {
        return $this === self::NotificatiesUrlInherited || $this === self::NotificatiesInstanceInUse;
    }

    public function isDanger(): bool
    {
        return in_array($this, [
            self::NoLocalRecord,
            self::SharedAbonnement,
            self::RemoteMissing,
            self::AuthMismatch,
            self::TokenExpired,
            self::TokenNotUsable,
            self::WebhookAuthMismatchExpiring,
        ], true);
    }

    /**
     * The snake_case key used to look up this status' translation strings.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::NoNotificatiesUrl => 'no_notificaties_url',
            self::NotificatiesUrlInherited => 'notificaties_url_inherited',
            self::NotificatiesInstanceInUse => 'notificaties_instance_in_use',
            self::NoLocalRecord => 'no_local_record',
            self::SharedAbonnement => 'shared_abonnement',
            self::RemoteMissing => 'remote_missing',
            self::AuthMismatch => 'auth_mismatch',
            self::KanalenMismatch => 'kanalen_mismatch',
            self::TokenExpired => 'token_expired',
            self::TokenNotUsable => 'token_not_usable',
            self::TokenExpiringSoon => 'token_expiring_soon',
            self::WebhookAuthMismatch => 'webhook_auth_mismatch',
            self::WebhookAuthMismatchExpiring => 'webhook_auth_mismatch_expiring',
            self::Healthy => 'healthy',
        };
    }
}
