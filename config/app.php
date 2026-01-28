<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'Europe/Amsterdam',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'nl'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'nl_NL'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | EventLoket specific configurations
    |--------------------------------------------------------------------------
    |
    */

    'require_2fa' => env('APP_REQUIRE_2FA', true),

    'api' => [
        'token_expire_in_days' => env('APP_API_TOKEN_EXPIRE_IN_DAYS', 365),
    ],

    'date_format' => env('APP_DATE_FORMAT', 'd-m-Y'),
    'datetime_format' => env('APP_DATETIME_FORMAT', 'd-m-Y H:i'),

    'document_mime_type_mappings' => [
        'eml' => 'message/rfc822',
        'emlx' => 'message/rfc822',
        'msg' => 'application/vnd.ms-outlook',

    ],
    'document_file_types' => env('APP_DOCUMENT_FILE_TYPES', [

        // Images
        'image/*',

        // PDF & text
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/rtf',

        // Word
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

        // Excel
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

        // PowerPoint
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',

        // OpenDocument formats
        'application/vnd.oasis.opendocument.text',        // .odt
        'application/vnd.oasis.opendocument.spreadsheet', // .ods
        'application/vnd.oasis.opendocument.presentation', // .odp

        // GPX & Geo formats
        'application/gpx+xml',
        'application/xml',              // GPX is XML-based
        'application/vnd.google-earth.kml+xml',  // .kml
        'application/vnd.google-earth.kmz',      // .kmz
        'application/geo+json',                  // .geojson
        'application/json',                      // sometimes used for geojson

        // CAD formats (widely used)
        'application/acad',                      // .dwg
        'application/x-acad',
        'application/x-dwg',
        'image/vnd.dwg',                         // DWG (sometimes reported as image/*)
        'application/dxf',                       // .dxf
        'application/x-dxf',
        'model/vnd.dwf',                         // .dwf
        'application/vnd.dwf',
        'application/vnd.autocad.dwg',

        // email formats
        'message/rfc822', // .eml .emlx
        'application/vnd.ms-outlook', // .msg
    ]),
];
