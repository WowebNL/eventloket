<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Laravel's global TrustProxies middleware falls back to this key when no
    | proxies are configured on the middleware itself, so this file is all it
    | takes to make the trusted-proxy list environment specific. See
    | Illuminate\Http\Middleware\TrustProxies::setTrustedProxyIpAddresses().
    |
    | Unset is the default and means "trust nothing", which is what production
    | runs on: nothing proxies it, so X-Forwarded-For is attacker controlled and
    | must be ignored. config/auth.php spells out why that matters, because the
    | login limiters key on request()->ip().
    |
    | Only set TRUSTED_PROXIES on an environment where a proxy really does
    | terminate every request and overwrites X-Forwarded-For itself, so a client
    | cannot smuggle in a forged address. An environment behind a
    | TLS-terminating proxy is the case this is for: until that proxy is
    | trusted the application generates http:// URLs on an https:// page, which
    | the content security policy then blocks.
    |
    | bootstrap/app.php narrows the trusted forwarded headers to X-Forwarded-For,
    | -Proto and -Port. X-Forwarded-Host is deliberately not trusted, so the proxy
    | only has to set For and Proto correctly and a forged host header can never
    | reach request()->getHost() or poison a generated URL (a password-reset link,
    | for example). The list below therefore governs only who is trusted, not
    | which headers.
    |
    | Accepts a comma separated list of addresses or CIDR ranges, the literal
    | "REMOTE_ADDR" to trust the immediate caller, or "*" to trust whoever calls.
    |
    | This lives in a config file rather than in an env() call inside the
    | withMiddleware() closure of bootstrap/app.php for two reasons. That closure
    | runs while the HTTP kernel is being resolved (Application::handleRequest()),
    | which is before the kernel bootstraps LoadEnvironmentVariables, so env()
    | returns null there and the setting would silently do nothing. And a config
    | file is also the only place a value survives `php artisan config:cache`.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
