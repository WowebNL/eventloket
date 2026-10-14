<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'horizon_webhook_url' => env('HORIZON_SLACK_WEBHOOK_URL'),
    ],

    'open_forms' => [
        'base_url' => env('OPEN_FORMS_BASE_URL'),
        'main_form_uuid' => env('OPEN_FORMS_MAIN_FORM_UUID'),
        'main_form_slug' => env('OPEN_FORMS_FORM_SLUG', 'evenementformulier-poc-kopie-a6efc0'),
        'prefill_object_type_url' => env('OPEN_FORMS_PREFILL_OBJECT_TYPE_URL'),
        'prefill_object_type_version' => env('OPEN_FORMS_PREFILL_OBJECT_TYPE_VERSION', '1'),
        'auteur_name' => env('OPEN_FORMS_AUTEUR_NAME', 'Aanvrager'),
        'admin_token' => env('OPEN_FORMS_ADMIN_TOKEN'),
    ],

    'kadaster' => [
        'base_url' => env('KADASTER_BASE_URL', 'https://api.pdok.nl/kadaster'),
    ],

    'openzaak' => [
        // RSIN van Veiligheidsregio Zuid-Limburg — bronorganisatie bij elke
        // nieuwe zaak in OpenZaak. In de oude OF-flow stond dit in alle 45
        // registratie-backends hardcoded op dezelfde waarde (820151130),
        // dus we repliceren dat hier in één plek.
        'bronorganisatie_rsin' => env('OPENZAAK_BRONORGANISATIE_RSIN', '820151130'),
    ],

    'locatieserver' => [
        'base_url' => env('LOCATIESERVER_BASE_URL', 'https://api.pdok.nl/bzk/locatieserver'),

        // Most Locatieserver lookups happen while a page or a Livewire update
        // is being rendered, so the caller is a user waiting on a response.
        // The framework defaults (10s connect, 30s total) are far too generous
        // for that: during a PDOK outage they hold the request open long
        // enough to exhaust PHP-FPM workers. PDOK answers in well under a
        // second in normal operation, so a 2s connect / 5s total budget leaves
        // an order of magnitude of headroom and still fails fast.
        'connect_timeout' => env('LOCATIESERVER_CONNECT_TIMEOUT', 2),
        'timeout' => env('LOCATIESERVER_TIMEOUT', 5),

        // Queued work has nobody waiting on it and a missed lookup silently
        // drops an address from the result, so there it is worth waiting out a
        // slow-but-alive Locatieserver. Opt in per caller with
        // LocatieserverService::forBackgroundWork().
        'background_connect_timeout' => env('LOCATIESERVER_BACKGROUND_CONNECT_TIMEOUT', 5),
        'background_timeout' => env('LOCATIESERVER_BACKGROUND_TIMEOUT', 20),
    ],
];
