<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The only API endpoint (/open-notifications/listen) is a server-to-server
    | ZGW webhook — no browser-originated cross-origin requests are expected.
    | All Filament panel routes are same-origin (Livewire SSR). CORS headers
    | are therefore scoped to api/* only and no external origins are allowed.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['POST'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
