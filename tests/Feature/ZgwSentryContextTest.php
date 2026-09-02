<?php

declare(strict_types=1);

/**
 * The bootstrap exception handler attaches the ZGW API response (status + body)
 * as a Sentry `zgw_response` context. ZGW validation errors keep their body off
 * the exception message on purpose (it can carry PII), which previously made a
 * "request failed validation [400]" undiagnosable in Sentry. These tests prove
 * the context is set for ZGW API errors and left untouched for other exceptions.
 */

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Response;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Woweb\Zgw\Exceptions\ValidationException;

/**
 * Read the contexts currently attached to the Sentry hub's scope by applying it
 * to a throwaway event.
 *
 * @return array<string, mixed>
 */
function currentSentryContexts(): array
{
    $contexts = [];

    SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use (&$contexts): void {
        $event = Event::createEvent();
        $scope->applyToEvent($event);
        $contexts = $event->getContexts();
    });

    return $contexts;
}

// The Sentry scope is a process-global singleton, so a context set by one test
// would leak into the next. Clear it before each test to keep them independent.
beforeEach(function () {
    SentrySdk::getCurrentHub()->configureScope(fn (Scope $scope) => $scope->removeContext('zgw_response'));
});

test('reporting a ZGW ValidationException attaches the response body as Sentry context', function () {
    $body = [
        'code' => 'invalid',
        'title' => 'Invalid input.',
        'invalidParams' => [
            ['name' => 'objectIdentificatie', 'code' => 'invalid', 'reason' => 'Onbekend veld.'],
        ],
    ];

    $response = new Response(new Psr7Response(400, [], json_encode($body)));
    $exception = new ValidationException('ZGW API request failed validation [400].', $response, 400);

    app(ExceptionHandler::class)->report($exception);

    $contexts = currentSentryContexts();

    expect($contexts)->toHaveKey('zgw_response');
    expect($contexts['zgw_response']['status'])->toBe(400);
    expect($contexts['zgw_response']['body'])->toContain('objectIdentificatie');
    expect($contexts['zgw_response']['body'])->toContain('Onbekend veld.');
});

test('a non-ZGW exception does not get a zgw_response context', function () {
    app(ExceptionHandler::class)->report(new RuntimeException('iets anders'));

    expect(currentSentryContexts())->not->toHaveKey('zgw_response');
});
