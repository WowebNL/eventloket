<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ZgwAbonnement;
use App\Services\Notificaties\StaticWebhookTokenIssuer;
use App\Services\Notificaties\WebhookAuthType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\Exceptions\AuthenticationException;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use League\OAuth2\Server\ResourceServer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the caller of the webhook and records which ZGW connection it
 * is.
 *
 * The webhook has to know who is calling, not just that the caller is someone.
 * A ZGW connection may only report about its own zaken, and the only trustworthy
 * statement of who called is the validated credential. Two credentials exist:
 *
 * - a scoped Passport access token, checked by Passport's resource-owner
 *   middleware (this class extends it rather than adding a second middleware,
 *   so the token is validated once), whose client identifies the connection
 *   through the abonnement row that stores it;
 * - a short static token (a bearer starting with "elw."), checked against its
 *   hashed secret, whose row names the connection directly.
 *
 * Either way one request attribute carries the connection name, set only after
 * the credential was verified. A static token that fails for any reason raises
 * the same exception as an invalid Passport token, so the response does not
 * tell which check was made.
 */
class IdentifyWebhookClient extends EnsureClientIsResourceOwner
{
    public const CONNECTION_ATTRIBUTE = 'zgw_webhook_connection';

    public function __construct(ResourceServer $server, private readonly StaticWebhookTokenIssuer $staticTokens)
    {
        parent::__construct($server);
    }

    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && StaticWebhookTokenIssuer::looksLikeStaticToken($bearer)) {
            $request->attributes->set(self::CONNECTION_ATTRIBUTE, $this->authenticateStaticToken($bearer));

            return $next($request);
        }

        $token = $this->validateToken($request);

        $this->validate($token, ...$params);

        $request->attributes->set(self::CONNECTION_ATTRIBUTE, $this->connectionOfClient($this->clientId($token)));

        return $next($request);
    }

    /**
     * The connection a static token belongs to.
     *
     * Only the token row decides whether it is accepted; the connection's current
     * setting does not, so changing the setting takes effect when the abonnement
     * is registered again. The one exception is a setting that is not a known
     * type: that is a configuration fault, and it is refused rather than guessed
     * at.
     *
     * @throws AuthenticationException
     */
    private function authenticateStaticToken(#[\SensitiveParameter] string $bearer): string
    {
        $row = $this->staticTokens->verify($bearer);

        if ($row === null) {
            throw new AuthenticationException;
        }

        if (WebhookAuthType::configuredFor($row->connection) === null) {
            Log::warning('Refused a webhook call: the webhook_auth setting of its ZGW connection is not a known credential type.', [
                'connection' => $row->connection,
            ]);

            throw new AuthenticationException;
        }

        return $row->connection;
    }

    /**
     * The connection whose abonnement stores this client. Each connection's
     * Passport webhook token is minted for its own one-off client, so this is
     * the same question as "which connection is this", answered without
     * trusting the caller. Null when the client matches no abonnement, which
     * leaves the notification unattributed.
     */
    private function connectionOfClient(?string $clientId): ?string
    {
        if ($clientId === null) {
            return null;
        }

        $connection = ZgwAbonnement::query()->where('client_id', $clientId)->value('connection');

        return is_string($connection) ? $connection : null;
    }

    private function clientId(ScopeAuthorizable $token): ?string
    {
        if (! $token instanceof AccessToken) {
            return null;
        }

        $clientId = $token->oauth_client_id;

        return is_string($clientId) && $clientId !== '' ? $clientId : null;
    }
}
