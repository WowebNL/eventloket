<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

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
        }

        return $response;
    }
}
