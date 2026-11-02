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
    case TokenExpiringSoon;
    case Healthy;

    public function isOk(): bool
    {
        return $this === self::Healthy;
    }

    public function isWarning(): bool
    {
        return in_array($this, [self::NoNotificatiesUrl, self::KanalenMismatch, self::TokenExpiringSoon], true);
    }

    public function isDanger(): bool
    {
        return in_array($this, [
            self::NotificatiesUrlInherited,
            self::NotificatiesInstanceInUse,
            self::NoLocalRecord,
            self::SharedAbonnement,
            self::RemoteMissing,
            self::AuthMismatch,
            self::TokenExpired,
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
            self::TokenExpiringSoon => 'token_expiring_soon',
            self::Healthy => 'healthy',
        };
    }
}
