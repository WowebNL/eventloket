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
        // request-pad naar het bijbehorende panel. Livewire-requests komen
        // binnen op /livewire/update (niet op een panel-pad), dus als
        // fallback controleren we de Referer-header.
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            $panels = ['admin', 'organiser', 'municipality', 'advisor'];

            foreach ($panels as $panelId) {
                if ($request->is($panelId, $panelId.'/*')) {
                    return Filament::getPanel($panelId)?->getLoginUrl();
                }
            }

            // Livewire AJAX-requests gaan naar /livewire/update en hebben
            // geen panel-pad, maar sturen wel een Referer mee die naar de
            // originele paginaURL verwijst. Gebruik die om het panel te bepalen.
            $refererPath = ltrim(
                parse_url($request->headers->get('referer', ''), PHP_URL_PATH) ?? '',
                '/'
            );

            foreach ($panels as $panelId) {
                if ($refererPath === $panelId || str_starts_with($refererPath, $panelId.'/')) {
                    return Filament::getPanel($panelId)?->getLoginUrl();
                }
            }

            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
