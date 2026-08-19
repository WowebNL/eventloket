<?php

use App\Support\Sentry\BreadcrumbScrubber;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// config/sentry.php reads its environment variables while it is evaluated, and the
// application configuration is already resolved by the time a test runs. These tests
// therefore evaluate the configuration file again instead of reading config('sentry'),
// so that toggling an environment variable is actually observable.

afterEach(function () {
    putenv('SENTRY_IGNORE_REDIS_EXCEPTIONS');
    unset($_ENV['SENTRY_IGNORE_REDIS_EXCEPTIONS'], $_SERVER['SENTRY_IGNORE_REDIS_EXCEPTIONS']);
});

/** Evaluate config/sentry.php with the environment as it is right now. */
function freshSentryConfig(): array
{
    return require config_path('sentry.php');
}

it('does not ignore RedisException unless the environment opts in', function () {
    expect(freshSentryConfig()['ignore_exceptions'])->toBe([
        AuthenticationException::class,
        ValidationException::class,
        NotFoundHttpException::class,
        MethodNotAllowedHttpException::class,
    ]);
});

it('ignores RedisException when the environment opts in', function () {
    putenv('SENTRY_IGNORE_REDIS_EXCEPTIONS=true');

    expect(freshSentryConfig()['ignore_exceptions'])->toBe([
        AuthenticationException::class,
        ValidationException::class,
        NotFoundHttpException::class,
        MethodNotAllowedHttpException::class,
        RedisException::class,
    ]);
});

it('removes the http.query metadata from a breadcrumb', function () {
    $beforeBreadcrumb = freshSentryConfig()['before_breadcrumb'];

    $breadcrumb = new Breadcrumb(
        Breadcrumb::LEVEL_INFO,
        Breadcrumb::TYPE_HTTP,
        'http',
        null,
        [
            'url' => 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free',
            'http.query' => 'q=postcode:1234AB+AND+huisnummer:5',
            'http.method' => 'GET',
            'http.response.status_code' => 200,
        ]
    );

    expect($beforeBreadcrumb($breadcrumb)->getMetadata())->toBe([
        'url' => 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free',
        'http.method' => 'GET',
        'http.response.status_code' => 200,
    ]);
});

it('leaves a breadcrumb without http.query metadata untouched', function () {
    $beforeBreadcrumb = freshSentryConfig()['before_breadcrumb'];

    $breadcrumb = new Breadcrumb(
        Breadcrumb::LEVEL_INFO,
        Breadcrumb::TYPE_DEFAULT,
        'cache',
        'Cache hit',
        ['key' => 'events:1']
    );

    expect($beforeBreadcrumb($breadcrumb))->toBe($breadcrumb);
});

it('keeps the breadcrumb scrubber serializable for config:cache', function () {
    // A closure here would make `php artisan config:cache` fail, so the callback
    // must stay an array callable that var_export() can write out.
    expect(freshSentryConfig()['before_breadcrumb'])->toBe([BreadcrumbScrubber::class, 'scrub']);
});

it('hands the breadcrumb scrubber to the Sentry client', function () {
    expect(app(ClientBuilder::class)->getOptions()->getBeforeBreadcrumbCallback())
        ->toBe([BreadcrumbScrubber::class, 'scrub']);
});
