<?php

declare(strict_types=1);

/**
 * PDOK's Locatieserver is a third party that goes down now and then. When it
 * does, a lookup must degrade to "no address" instead of throwing: the callers
 * all render a page or a Livewire update, and an exception there takes the
 * whole screen down. On top of that the requests carry an explicit timeout
 * budget, because the framework defaults (10s connect, 30s total) keep a
 * request alive far too long while a user is waiting on it.
 */

use App\Services\LocatieserverService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Config::set('services.locatieserver.base_url', 'https://locatieserver.test');
});

/**
 * Fake a Locatieserver that never answers. A connect failure and a timeout
 * both surface as this exception: Guzzle maps CURLE_OPERATION_TIMEOUTED,
 * CURLE_COULDNT_CONNECT and CURLE_COULDNT_RESOLVE_HOST onto a ConnectException,
 * which the Laravel client rethrows as ConnectionException.
 */
function fakeUnreachablePdok(string $message = 'cURL error 28: Operation timed out'): void
{
    Http::fake(function () use ($message) {
        throw new ConnectionException($message.' for https://locatieserver.test/search/v3_1/lookup?id=0123456789');
    });
}

/**
 * Record the Guzzle options of the next request. The fake stub callback is
 * handed the resolved options, which is where the timeouts end up.
 *
 * @return ArrayObject<string, mixed> filled in once the request is made
 */
function captureRequestOptions(): ArrayObject
{
    /** @var ArrayObject<string, mixed> $options */
    $options = new ArrayObject;

    Http::fake(function (Request $request, array $requestOptions) use ($options) {
        $options->exchangeArray($requestOptions);

        return Http::response(['response' => ['docs' => []]]);
    });

    return $options;
}

test('a lookup by BAG id degrades to null when the Locatieserver cannot be reached', function () {
    fakeUnreachablePdok();

    expect((new LocatieserverService)->getBagObjectById('0123456789'))->toBeNull();
});

test('a lookup by postcode and house number degrades to null when the Locatieserver cannot be reached', function () {
    fakeUnreachablePdok();

    expect((new LocatieserverService)->getBagObjectByPostcodeHuisnummer('6361BZ', '1'))->toBeNull();
});

test('a BRK identification lookup degrades to null when the Locatieserver cannot be reached', function () {
    fakeUnreachablePdok();

    expect((new LocatieserverService)->getBrkIdentificationByPostcodeHuisnummer('6361BZ', '1'))->toBeNull();
});

test('a reverse lookup degrades to null when the Locatieserver cannot be reached', function () {
    fakeUnreachablePdok();

    expect((new LocatieserverService)->reverse(50.91, 5.88))->toBeNull();
});

test('a lookup degrades to null when the Locatieserver answers with a server error', function () {
    Http::fake([
        'https://locatieserver.test/*' => Http::response('Service Unavailable', 503),
    ]);

    expect((new LocatieserverService)->getBagObjectById('0123456789'))->toBeNull();
});

test('the failure is logged without the queried address', function () {
    fakeUnreachablePdok();
    Log::spy();

    (new LocatieserverService)->getBagObjectById('0123456789');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) {
            // The full request URI carries the address that was looked up, so
            // it must stay out of the log.
            return $context['path'] === '/search/v3_1/lookup'
                && str_contains($context['reason'], 'Operation timed out')
                && ! str_contains($context['reason'], 'locatieserver.test');
        });
});

test('requests carry the interactive timeout budget', function () {
    Config::set('services.locatieserver.connect_timeout', 2);
    Config::set('services.locatieserver.timeout', 5);

    $options = captureRequestOptions();

    (new LocatieserverService)->getBagObjectById('0123456789');

    expect($options['connect_timeout'])->toEqual(2.0)
        ->and($options['timeout'])->toEqual(5.0);
});

test('background callers can opt in to a longer timeout budget', function () {
    Config::set('services.locatieserver.background_connect_timeout', 5);
    Config::set('services.locatieserver.background_timeout', 20);

    $options = captureRequestOptions();

    (new LocatieserverService)->forBackgroundWork()->getBagObjectById('0123456789');

    expect($options['connect_timeout'])->toEqual(5.0)
        ->and($options['timeout'])->toEqual(20.0);
});

test('an empty timeout setting falls back to the default budget', function () {
    // An operator who blanks out LOCATIESERVER_TIMEOUT gets an empty string,
    // and casting that straight to float would hand Guzzle a 0 it reads as
    // "wait indefinitely" — worse than the framework defaults this replaces.
    Config::set('services.locatieserver.connect_timeout', '');
    Config::set('services.locatieserver.timeout', '');

    $options = captureRequestOptions();

    (new LocatieserverService)->getBagObjectById('0123456789');

    expect($options['connect_timeout'])->toEqual(2.0)
        ->and($options['timeout'])->toEqual(5.0);
});

test('timeout settings missing from the config fall back to the default budget', function () {
    // What a config cache built before these keys existed looks like.
    Config::set('services.locatieserver', ['base_url' => 'https://locatieserver.test']);

    $options = captureRequestOptions();

    (new LocatieserverService)->getBagObjectById('0123456789');

    expect($options['connect_timeout'])->toEqual(2.0)
        ->and($options['timeout'])->toEqual(5.0);
});

test('the background budget falls back to its own defaults when the config is missing', function () {
    Config::set('services.locatieserver', ['base_url' => 'https://locatieserver.test']);

    $options = captureRequestOptions();

    (new LocatieserverService)->forBackgroundWork()->getBagObjectById('0123456789');

    expect($options['connect_timeout'])->toEqual(5.0)
        ->and($options['timeout'])->toEqual(20.0);
});

test('a timeout configured below the floor is raised to it instead of left unlimited', function () {
    Config::set('services.locatieserver.connect_timeout', 0);
    Config::set('services.locatieserver.timeout', 0);

    $options = captureRequestOptions();

    (new LocatieserverService)->getBagObjectById('0123456789');

    expect($options['connect_timeout'])->toBeGreaterThan(0.0)
        ->and($options['timeout'])->toBeGreaterThan(0.0);
});

test('a caller can tell an unreachable Locatieserver from an address it does not know', function () {
    fakeUnreachablePdok();

    $service = new LocatieserverService;
    $service->getBagObjectById('0123456789');

    expect($service->lastRequestWasUnreachable())->toBeTrue();
});

test('an answered request is not reported as unreachable', function () {
    Http::fake([
        'https://locatieserver.test/*' => Http::response('Service Unavailable', 503),
    ]);

    $service = new LocatieserverService;
    $service->getBagObjectById('0123456789');

    // PDOK answered, so there is nothing to come back for; only a transport
    // failure leaves the question genuinely unanswered.
    expect($service->lastRequestWasUnreachable())->toBeFalse();
});

test('opting in to the background budget leaves the original service untouched', function () {
    $service = new LocatieserverService;
    $service->forBackgroundWork();

    $options = captureRequestOptions();

    $service->getBagObjectById('0123456789');

    expect($options['timeout'])->toEqual((float) config('services.locatieserver.timeout'));
});
