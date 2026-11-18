<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

/**
 * The outcome of {@see WebhookIngressHealthCheck::run()}: how the shared Open
 * Notificaties webhook is behaving as an endpoint, independent of whether the
 * abonnementen behind it are correctly registered.
 */
enum WebhookIngressStatus
{
    /**
     * Deliveries are being refused and have been for long enough that this is a
     * standing fault rather than a single failed attempt.
     */
    case Rejecting;

    /**
     * Nothing has been accepted for long enough that a registered abonnement is
     * no longer plausibly just quiet.
     */
    case Silent;

    case Healthy;

    public function isOk(): bool
    {
        return $this === self::Healthy;
    }
}
