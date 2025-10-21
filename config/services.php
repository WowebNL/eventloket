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
    ],

    'open_forms' => [
        'base_url' => env('OPEN_FORMS_BASE_URL'),
        'main_form_uuid' => env('OPEN_FORMS_MAIN_FORM_UUID'),
        'prefill_object_type_url' => env('OPEN_FORMS_PREFILL_OBJECT_TYPE_URL'),
        'prefill_object_type_version' => env('OPEN_FORMS_PREFILL_OBJECT_TYPE_VERSION', '1'),
        'auteur_name' => env('OPEN_FORMS_AUTEUR_NAME', 'Aanvrager'),
    ],

    'kadaster' => [
        'base_url' => env('KADASTER_BASE_URL', 'https://api.pdok.nl/kadaster'),
    ],

    'locatieserver' => [
        'base_url' => env('LOCATIESERVER_BASE_URL', 'https://api.pdok.nl/bzk/locatieserver'),
    ],
];
