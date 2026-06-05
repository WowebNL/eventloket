<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array(config('app.env'), ['local', 'testing']) && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->buildCsp());
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if (! in_array(config('app.env'), ['local', 'testing'])) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function buildCsp(): string
    {
        // connect-src must allow the Vite HMR WebSocket in local dev and the PDOK Locatieserver API.
        $connectSrc = "'self' https://api.pdok.nl";
        if (config('app.debug')) {
            $connectSrc .= ' ws://localhost:5173 ws://127.0.0.1:5173 http://localhost:5173';
        }

        $directives = [
            "default-src 'self'",
            // Alpine.js evaluates x-data/x-bind expressions via new Function(),
            // which requires 'unsafe-eval'. Livewire injects inline <script> tags,
            // requiring 'unsafe-inline'. Both are unavoidable without a nonce-based
            // approach and full Alpine.js CSP build.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            // Alpine generates inline style attributes at runtime.
            "style-src 'self' 'unsafe-inline'",
            // data: for locally-generated SVG avatar data URIs; blob: for
            // any canvas/object-URL created by UI components.
            "img-src 'self' data: blob: https://tile.openstreetmap.org",
            "font-src 'self'",
            "connect-src {$connectSrc}",
            // Prevent this application from being embedded anywhere.
            "frame-ancestors 'none'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        if (! in_array(config('app.env'), ['local', 'testing'])) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
