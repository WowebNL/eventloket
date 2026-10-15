<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport's resource-owner check, plus the one thing it throws away: which
 * client presented the token.
 *
 * The webhook has to know who is calling, not just that the caller is someone.
 * A ZGW connection may only report about its own zaken, and the only trustworthy
 * statement of who called is the validated token. Passport validates it inside
 * the middleware and discards it, so this subclass keeps the client id on the
 * request for the controller to attribute the notification with.
 *
 * Deliberately a subclass rather than a second middleware: validating the token
 * twice would be both wasteful and a chance for the two answers to differ.
 */
class IdentifyWebhookClient extends EnsureClientIsResourceOwner
{
    public const CLIENT_ID_ATTRIBUTE = 'oauth_client_id';

    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $token = $this->validateToken($request);

        $this->validate($token, ...$params);

        $request->attributes->set(self::CLIENT_ID_ATTRIBUTE, $this->clientId($token));

        return $next($request);
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
