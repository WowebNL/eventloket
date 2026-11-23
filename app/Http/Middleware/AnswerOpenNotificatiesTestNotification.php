<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers the test notification Open Notificaties sends while it creates or
 * updates an abonnement, to check that the callback accepts the abonnement's
 * credential.
 *
 * That notification has the channel "test" and placeholder URLs that are not
 * ZGW resources, so the request validation would refuse it and the abonnement
 * would not be saved. It runs after the credential has been authenticated and
 * ends the request there with a 204: the request validation, the controller,
 * the dispatch, the request log and the ingress bookkeeping are never reached.
 * Nothing else in the payload is read, so its URLs are never fetched or
 * trusted, and "test" never becomes an accepted channel further down.
 */
class AnswerOpenNotificatiesTestNotification
{
    public const TEST_CHANNEL = 'test';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->json('kanaal') !== self::TEST_CHANNEL) {
            return $next($request);
        }

        Log::info('Answered an Open Notificaties test notification.', [
            'connection' => $request->attributes->get(IdentifyWebhookClient::CONNECTION_ATTRIBUTE),
        ]);

        return response()->noContent();
    }
}
