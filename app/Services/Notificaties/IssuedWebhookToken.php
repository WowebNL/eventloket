<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use Carbon\CarbonImmutable;

/**
 * A freshly issued scoped Passport access token for the notificaties webhook.
 *
 * The {@see token} is the signed JWT that goes into an abonnement's `auth`
 * field; the remote Open Notificaties API presents it back on our shared
 * webhook, where it is validated as a client token with notifications:receive.
 */
final class IssuedWebhookToken
{
    public function __construct(
        public readonly string $token,
        public readonly ?string $tokenId,
        public readonly string $clientId,
        public readonly ?CarbonImmutable $expiresAt,
    ) {}
}
