<?php

namespace App\Support\Sentry;

use Sentry\Breadcrumb;

/**
 * Removes personal data from Sentry breadcrumbs before they are recorded.
 *
 * The SDK's HTTP client integration strips the query string from the breadcrumb
 * URL, but adds it back as a separate `http.query` metadata field. Our address
 * lookups against PDOK carry a postcode and house number in that query string, so
 * without this scrub every breadcrumb of such a request ships personal data to
 * Sentry.
 *
 * Referenced from config/sentry.php as an array callable instead of a closure,
 * because the configuration has to stay serializable for `php artisan config:cache`.
 */
final class BreadcrumbScrubber
{
    public static function scrub(Breadcrumb $breadcrumb): Breadcrumb
    {
        return $breadcrumb->withoutMetadata('http.query');
    }
}
