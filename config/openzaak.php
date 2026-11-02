<?php

/*
|--------------------------------------------------------------------------
| Legacy single-connection ZGW settings
|--------------------------------------------------------------------------
|
| These keys used to be provided by the woweb/openzaak package. The
| application still reads them to recognise the hosts of the main ZGW
| backend (outgoing error logging, the notification host allowlist and the
| Objects API link on a zaak) and for the catalogus of the doorkomst import.
|
| The environment variable names and defaults are unchanged, so existing
| environments keep resolving to the same values. API calls go through
| woweb/laravel-zgw-client, configured in config/zgw.php.
|
*/

return [

    'url' => env('OPENZAAK_URL', 'https://openzaak.woweb.app/'),

    'catalogi_url' => env('OPENZAAK_CATALOGI_URL', null),

    /*
    | Per-API base URL overrides, for a backend that hosts each API at a
    | separate endpoint.
    */
    'besluiten_base_url' => env('OPENZAAK_BESLUITEN_BASE_URL', null),
    'catalogi_base_url' => env('OPENZAAK_CATALOGI_BASE_URL', null),
    'documenten_base_url' => env('OPENZAAK_DOCUMENTEN_BASE_URL', null),
    'zaken_base_url' => env('OPENZAAK_ZAKEN_BASE_URL', null),

    'openklant' => [
        'url' => env('OPENKLANT_URL', 'https://openklant.woweb.app/'),
    ],

    'objectsapi' => [
        'url' => env('OBJECTSAPI_URL', 'https://objects-api.woweb.app/'),
    ],

];
