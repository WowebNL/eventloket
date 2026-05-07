<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Standaard valt Laravel's `AuthenticationException::redirectTo()`
        // terug op `route('login')` — die route bestaat niet, want we
        // gebruiken Filament-panel-logins (`/admin/login`,
        // `/organiser/login`, …). Zonder deze callback gooit elke
        // unauth Livewire-roundtrip een `RouteNotFoundException` ([login])
        // in de exception-handler, wat de log volpompt tijdens form-
        // submissions wanneer de sessie net verlopen is. We mappen het
        // request-pad naar het bijbehorende panel.
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            foreach (['admin', 'organiser', 'municipality', 'advisor'] as $panelId) {
                if ($request->is($panelId, $panelId.'/*')) {
                    return Filament::getPanel($panelId)?->getLoginUrl();
                }
            }

            return null; // geen Filament-panel → laat de default afhandelen
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
