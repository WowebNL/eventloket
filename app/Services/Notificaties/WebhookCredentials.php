<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * The life cycle of a connection's webhook credential, shared by registration
 * and the renewal job: issue a credential of the configured type, make it
 * permanent once the Notificaties API accepted it, discard it when it did not,
 * and retire the credentials it replaces.
 *
 * Retiring follows the credentials themselves, not the current setting: the
 * Passport client and token stored on the previous abonnement row are revoked,
 * and the connection's other static tokens are retired. After a rotation within
 * the static type the previous token keeps working for a short overlap; after a
 * switch of type the previous credential is revoked at once.
 */
class WebhookCredentials
{
    /**
     * Upper bound on how long one registration or renewal may hold the lock of
     * its connection, and how long another one waits for it.
     */
    private const LOCK_SECONDS = 120;

    private const LOCK_WAIT_SECONDS = 15;

    public function __construct(
        private readonly WebhookTokenIssuer $passport,
        private readonly StaticWebhookTokenIssuer $static,
    ) {}

    public function issue(string $connectionName, WebhookAuthType $type): IssuedWebhookToken
    {
        return match ($type) {
            WebhookAuthType::Passport => $this->passport->issue(),
            WebhookAuthType::Static => $this->static->issue($connectionName),
        };
    }

    /**
     * The Notificaties API accepted the credential. A Passport token is valid
     * from the moment it is issued; a static token now gets its full lifetime.
     */
    public function activate(IssuedWebhookToken $token): void
    {
        if ($token->type === WebhookAuthType::Static) {
            $this->static->activate($token);
        }
    }

    /**
     * The registration failed, so the credential just issued is retired rather
     * than left behind.
     */
    public function discard(IssuedWebhookToken $token): void
    {
        match ($token->type) {
            WebhookAuthType::Passport => $this->passport->revoke($token->tokenId, $token->clientId),
            WebhookAuthType::Static => $this->static->discard($token),
        };
    }

    /**
     * Retire what the new credential replaces.
     */
    public function retirePrevious(string $connectionName, ?string $previousTokenId, ?string $previousClientId, IssuedWebhookToken $new): void
    {
        $this->passport->revoke($previousTokenId, $previousClientId);

        $this->static->retireOthers(
            $connectionName,
            $new->staticTokenId,
            withOverlap: $new->type === WebhookAuthType::Static,
        );
    }

    /**
     * Run a registration or renewal for one connection while no other one runs
     * for it, so two rotations cannot retire each other's credential.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLock(string $connectionName, callable $callback): mixed
    {
        try {
            return Cache::lock('zgw-abonnement-credential:'.$connectionName, self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, $callback);
        } catch (LockTimeoutException) {
            throw new RuntimeException("Another registration or renewal of the abonnement for ZGW connection \"{$connectionName}\" is running; try again later.");
        }
    }

    /**
     * The exception to pass on after a failed outgoing request, with the
     * plaintext credential removed from its message.
     *
     * An HTTP client exception carries part of the response body in its
     * message, and a Notificaties API may echo the `auth` value it was sent.
     * The message goes to the console, the log and the error tracker, so the
     * credential is replaced before it gets there. The client truncates that
     * body, which can cut a token anywhere, so for a static token anything that
     * starts like one is removed as well, however short. The original exception
     * is not chained, because its message would travel along with it.
     */
    public static function redact(Throwable $e, #[\SensitiveParameter] IssuedWebhookToken $token): Throwable
    {
        $message = $e->getMessage();
        $redacted = str_replace($token->token, '[redacted]', $message);

        if ($token->type === WebhookAuthType::Static) {
            $parts = explode('.', $token->token);
            $redacted = str_replace((string) end($parts), '[redacted]', $redacted);
            $redacted = (string) preg_replace('/'.preg_quote(StaticWebhookTokenIssuer::PREFIX, '/').'\.[0-9a-f]*(?:\.[A-Za-z0-9_-]*)?/', '[redacted]', $redacted);
        }

        if ($redacted === $message) {
            return $e;
        }

        return new RuntimeException($redacted, (int) $e->getCode());
    }
}
