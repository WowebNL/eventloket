<?php

/*
|--------------------------------------------------------------------------
| ZGW client configuration (woweb/laravel-zgw-client)
|--------------------------------------------------------------------------
|
| The "main" connection mirrors the legacy woweb/openzaak setup so the
| application keeps talking to the same OpenZaak instance after the swap.
| Per-municipality connections are registered at runtime by
| App\Services\Zgw\ZgwConnectionResolver, inheriting the defaults below.
|
| Base URLs are the full URL of each ZGW API, including the version path and
| a trailing slash. They default to the legacy OPENZAAK_URL host plus the
| standard component path, and can be overridden per API with ZGW_*_BASE_URL.
|
| NOTE: the client_secret is the HS256 signing key and must be at least 32
| bytes. firebase/php-jwt 7 refuses to sign with a shorter key, so the
| OpenZaak credential has to meet that floor before live calls succeed.
|
*/

$openzaakBase = rtrim((string) env('OPENZAAK_URL', ''), '/');

$component = static function (string $env, string $path) use ($openzaakBase): string {
    $explicit = env($env);

    if (is_string($explicit) && $explicit !== '') {
        return $explicit;
    }

    return $openzaakBase === '' ? '' : $openzaakBase.$path;
};

return [

    'default' => env('ZGW_CONNECTION', 'main'),

    'connections' => [

        'main' => [

            'urls' => [
                'zaken' => $component('ZGW_ZAKEN_BASE_URL', '/zaken/api/v1/'),
                'catalogi' => $component('ZGW_CATALOGI_BASE_URL', '/catalogi/api/v1/'),
                'documenten' => $component('ZGW_DOCUMENTEN_BASE_URL', '/documenten/api/v1/'),
                'besluiten' => $component('ZGW_BESLUITEN_BASE_URL', '/besluiten/api/v1/'),
                'autorisaties' => $component('ZGW_AUTORISATIES_BASE_URL', '/autorisaties/api/v1/'),
                'notificaties' => (string) env('ZGW_NOTIFICATIES_BASE_URL', ''),
            ],

            // Target ZGW standard release. Default 1.5 for now; overridable per connection.
            'version' => env('ZGW_VERSION', '1.5'),

            'client_id' => env('ZGW_CLIENT_ID', env('OPENZAAK_CLIENT_ID', '')),
            'client_secret' => env('ZGW_CLIENT_SECRET', env('OPENZAAK_CLIENT_SECRET', '')),
            'user_id' => env('ZGW_USER_ID', env('OPENZAAK_CLIENT_ID', '')),
            'user_representation' => env('ZGW_USER_REPRESENTATION', 'Eventloket'),

            'jwt_expiry' => (int) env('ZGW_JWT_EXPIRY', 300),

            'secret_rules' => [
                'min_length' => (int) env('ZGW_SECRET_MIN_LENGTH', 32),
                'require_uppercase' => env('ZGW_SECRET_REQUIRE_UPPERCASE', false),
                'require_lowercase' => env('ZGW_SECRET_REQUIRE_LOWERCASE', false),
                'require_number' => env('ZGW_SECRET_REQUIRE_NUMBER', false),
                'require_symbol' => env('ZGW_SECRET_REQUIRE_SYMBOL', false),
            ],

            'cache_store' => env('ZGW_CACHE_STORE'),

            'accept_crs' => env('ZGW_ACCEPT_CRS', 'EPSG:4326'),
            'content_crs' => env('ZGW_CONTENT_CRS', 'EPSG:4326'),

            'allowed_hosts' => array_filter([
                env('ZGW_DOCUMENTEN_INHOUD_HOST'),
            ]),

            'connect_timeout' => (int) env('ZGW_CONNECT_TIMEOUT', 10),
            'timeout' => (int) env('ZGW_TIMEOUT', 30),
            'max_pages' => (int) env('ZGW_MAX_PAGES', 1000),

            'retry_times' => (int) env('ZGW_RETRY_TIMES', 0),
            'retry_sleep_ms' => (int) env('ZGW_RETRY_SLEEP_MS', 100),
            'retry_max_sleep_ms' => (int) env('ZGW_RETRY_MAX_SLEEP_MS', 5000),

            /*
            | Application-level parameters (read by Eventloket, ignored by the package).
            | These differ per ZGW instance and default to the legacy OpenZaak behaviour.
            */

            // RSIN used as bronorganisatie / verantwoordelijkeOrganisatie on every zaak.
            'bronorganisatie_rsin' => env('OPENZAAK_BRONORGANISATIE_RSIN', '820151130'),

            // Optional date format for zaakeigenschap string values (e.g. 'YmdHis' for RX
            // Mission). Null keeps the value as produced by the form (legacy behaviour).
            'eigenschap_date_format' => env('ZGW_EIGENSCHAP_DATE_FORMAT'),

        ],

    ],

    /*
    | Retention window (in days) for the ZGW request log. The daily
    | zgw:prune-request-logs command deletes rows older than this. The rows hold
    | metadata only (no bodies, query string stripped), but the window is kept
    | configurable so it can be tied to the organisation's logging policy.
    */
    'request_log_retention_days' => (int) env('ZGW_REQUEST_LOG_RETENTION_DAYS', 90),

];
