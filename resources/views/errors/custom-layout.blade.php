<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        <!-- Fonts -->
        <link href="{{ asset('css/fonts/satoshi.css') }}" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center sm:pt-0">
            <div class="max-w-3xl mx-auto ">
                <div class="flex items-center pt-8 sm:justify-start sm:pt-0">
                    <div class="ml-4 text-lg text-gray-500  tracking-wider">
                        <small>@yield('code')</small>
                        <hr>
                        @yield('message')
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>