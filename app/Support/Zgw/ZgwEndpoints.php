<?php

declare(strict_types=1);

namespace App\Support\Zgw;

/**
 * Decides whether an outgoing HTTP call targets one of the ZGW (Common Ground) APIs this
 * application is configured to talk to.
 *
 * Error logging is deliberately limited to those hosts. The application also calls PDOK,
 * the Kadaster, OSM tile servers and a Slack webhook; the webhook in particular carries its
 * secret in the URL path, so logging failures of "anything outgoing" would leak a credential.
 *
 * Two configuration shapes are read, so the resolver keeps working when the application moves
 * from the single-connection woweb/openzaak package to the multi-connection
 * woweb/laravel-zgw-client package:
 *  - woweb/openzaak: one base URL plus optional per-API overrides;
 *  - woweb/laravel-zgw-client: `zgw.connections.<name>.urls.<api>`, which is also the shape
 *    runtime-registered connections are written into.
 */
final class ZgwEndpoints
{
    /**
     * Whether the given absolute request URL sits under a configured ZGW base URL.
     */
    public static function isZgwUrl(string $url): bool
    {
        $candidate = rtrim($url, '/').'/';

        foreach (self::baseUrls() as $baseUrl) {
            if (str_starts_with($candidate, $baseUrl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every configured ZGW base URL, each normalised to a single trailing slash so that a
     * prefix match cannot spill over into a look-alike host.
     *
     * @return list<string>
     */
    public static function baseUrls(): array
    {
        $candidates = [
            config('openzaak.url'),
            config('openzaak.zaken_base_url'),
            config('openzaak.catalogi_base_url'),
            config('openzaak.documenten_base_url'),
            config('openzaak.besluiten_base_url'),
            config('openzaak.catalogi_url'),
            config('openzaak.openklant.url'),
            config('openzaak.objectsapi.url'),
        ];

        foreach ((array) config('zgw.connections', []) as $connection) {
            foreach ((array) ($connection['urls'] ?? []) as $url) {
                $candidates[] = $url;
            }
        }

        $baseUrls = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $baseUrls[] = rtrim(trim($candidate), '/').'/';
        }

        return array_values(array_unique($baseUrls));
    }
}
