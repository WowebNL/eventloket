<?php

use App\Http\Middleware\SecurityHeaders;
use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Woweb\Zgw\Exceptions\ApiRequestException;

use function Sentry\configureScope;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

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
        // ZGW write failures (typically HTTP 400 ValidatieFout) keep the response
        // body off the exception message on purpose, because it can carry PII. That
        // makes them undiagnosable in Sentry: we only see "request failed validation
        // [400]" with no field-level detail. Attach the response status and body as a
        // Sentry context so we can see *what* the ZGW API rejected without reproducing
        // the request. Registered before Integration::handles() so the scope is set
        // before Sentry captures the event; returning void keeps default reporting.
        $exceptions->report(function (ApiRequestException $e): void {
            configureScope(function (Scope $scope) use ($e): void {
                $response = $e->getResponse();

                $scope->setContext('zgw_response', [
                    'status' => $response->status(),
                    // Cap the body so a stray large payload can't bloat the event.
                    'body' => mb_substr($response->body(), 0, 4000),
                ]);
            });
        });

        Integration::handles($exceptions);
    })->create();
