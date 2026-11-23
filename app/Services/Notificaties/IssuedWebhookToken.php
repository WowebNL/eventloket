<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use Carbon\CarbonImmutable;

/**
 * A freshly issued credential for the notificaties webhook.
 *
 * For {@see WebhookAuthType::Passport} the {@see token} is a signed JWT, a
 * scoped Passport access token validated as a client token with
 * notifications:receive. For {@see WebhookAuthType::Static} it is a short
 * opaque token whose hashed secret lives in the row {@see staticTokenId}.
 *
 * The plaintext token only exists between issuance and the outgoing request
 * that hands it to the Notificaties API. It must never be queued, put in an
 * event or passed as log context.
 */
final class IssuedWebhookToken
{
    public function __construct(
        #[\SensitiveParameter]
        public readonly string $token,
        public readonly ?string $tokenId,
        public readonly ?string $clientId,
        public readonly ?CarbonImmutable $expiresAt,
        public readonly WebhookAuthType $type = WebhookAuthType::Passport,
        public readonly ?int $staticTokenId = null,
    ) {}

    public function __serialize(): array
    {
        throw new \LogicException('A webhook token must not be serialised.');
    }
}
