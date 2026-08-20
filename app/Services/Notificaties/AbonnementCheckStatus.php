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
    case NoLocalRecord;
    case RemoteMissing;
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
        return in_array($this, [self::NoLocalRecord, self::RemoteMissing, self::TokenExpired], true);
    }

    /**
     * The snake_case key used to look up this status' translation strings.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::NoNotificatiesUrl => 'no_notificaties_url',
            self::NoLocalRecord => 'no_local_record',
            self::RemoteMissing => 'remote_missing',
            self::KanalenMismatch => 'kanalen_mismatch',
            self::TokenExpired => 'token_expired',
            self::TokenExpiringSoon => 'token_expiring_soon',
            self::Healthy => 'healthy',
        };
    }
}
