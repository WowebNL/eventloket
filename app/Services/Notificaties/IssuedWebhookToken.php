<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use Carbon\CarbonImmutable;
use JsonSerializable;

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
final class IssuedWebhookToken implements JsonSerializable
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

    /**
     * What json_encode() sees, for instance when the object ends up in a log
     * context: everything but the token itself.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->withoutToken();
    }

    /**
     * What var_dump() and print_r() show.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->withoutToken();
    }

    /**
     * @return array<string, mixed>
     */
    private function withoutToken(): array
    {
        return [
            'token' => '[redacted]',
            'tokenId' => $this->tokenId,
            'clientId' => $this->clientId,
            'expiresAt' => $this->expiresAt?->toIso8601String(),
            'type' => $this->type->value,
            'staticTokenId' => $this->staticTokenId,
        ];
    }
}
