<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

/**
 * Why a connection may not register its own abonnement: its Notificaties API is
 * the one another connection already subscribes on.
 *
 * Every connection subscribes with the same callback URL, so a Notificaties API
 * can only carry one abonnement of ours without delivering each notification
 * twice. The webhook token on that abonnement identifies the connection a
 * notification is attributed to, which makes a second registration on the same
 * instance a takeover of the first connection's attribution rather than a
 * harmless refresh.
 */
final readonly class SharedNotificatiesInstance
{
    public function __construct(
        /** The connection that holds the abonnement on this Notificaties API. */
        public string $owner,
        /** True when the URL was left empty and inherited from the main connection. */
        public bool $inherited,
    ) {}
}
