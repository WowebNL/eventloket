<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ZgwWebhookIngressState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs rejected calls to the public Open Notificaties webhook so credential
 * stuffing or SSRF probing against the application's only internet-facing
 * ingress is detectable.
 *
 * Runs ahead of the Passport scope middleware and the request validation. Those
 * layers raise exceptions that Laravel's routing pipeline renders into error
 * responses, so this middleware inspects the resulting status rather than
 * catching exceptions: 401 (missing or invalid token), 403 (insufficient scope)
 * and 422 (a host-rule / SSRF-guard or payload validation failure). Only metadata
 * is logged (source IP, user agent, path, status); request bodies and URLs are
 * never logged because ZGW query filters can carry personal data.
 *
 * A rejection that arrived with a credential is also counted on the ingress
 * state, so a standing refusal becomes visible to the scheduled health check. A
 * log line is a trace, not a signal: without something keeping score, a webhook
 * that refuses every delivery looks exactly like one that is simply quiet.
 */
class LogOpenNotificationRejection
{
    /**
     * Response statuses that represent a rejected webhook call, mapped to the
     * security-event category they belong to.
     */
    private const REJECTION_CATEGORIES = [
        401 => 'authentication',
        403 => 'authorization',
        422 => 'validation',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $category = self::REJECTION_CATEGORIES[$response->getStatusCode()] ?? null;

        if ($category !== null) {
            Log::warning('Rejected Open Notificaties webhook call.', [
                'category' => $category,
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
            ]);

            $this->countAgainstIngress($request, $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Count a rejection towards the ingress state, but only when the caller
     * presented a credential.
     *
     * A public endpoint collects unauthenticated probes as a matter of course.
     * Counting those would make the health signal fire on background noise, and a
     * signal that fires on noise gets ignored, which is the failure this is meant
     * to prevent. A call that carries an Authorization header is either the
     * registered sender or something holding a credential; both are worth
     * knowing about, and neither is routine.
     *
     * Whether a credential was presented is read from the header's presence
     * alone. Its value is never inspected, logged or stored.
     *
     * Keeping score must never change the response: a failure to record is
     * logged, and the caller still gets the rejection it earned rather than a
     * server error.
     */
    private function countAgainstIngress(Request $request, int $status): void
    {
        if (! $request->hasHeader('Authorization')) {
            return;
        }

        try {
            ZgwWebhookIngressState::recordRejection($status);
        } catch (Throwable $e) {
            Log::warning('Failed to record a rejected call on the webhook ingress state.', ['exception' => $e->getMessage()]);
        }
    }
}
