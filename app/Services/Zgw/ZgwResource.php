<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Woweb\Zgw\Api\Endpoints\DirectEndpoint;
use Woweb\Zgw\Facades\Zgw;

/**
 * Small bridge over the ZGW client for single-resource reads.
 *
 * The new package derives a `uuid` from the `url` for list items, but not for single
 * fetches (show/getByUrl) or writes (store). The legacy woweb/openzaak client derived it
 * everywhere, and the application's value objects expect a `uuid`. This helper restores
 * that parity while the value objects are still in use (until the typed DTO adoption).
 */
class ZgwResource
{
    /**
     * Fetch a single ZGW resource by its full URL, deriving the uuid like the legacy client.
     *
     * @return array<string, mixed>
     */
    public static function byUrl(string $connectionName, string $url): array
    {
        $connection = Zgw::connection($connectionName);

        return self::ensureUuid((new DirectEndpoint($connection))->getByUrl($url));
    }

    /**
     * Download the raw bytes at a full ZGW URL (e.g. a document `inhoud` link).
     *
     * Used for current-version downloads where only the resource `inhoud` URL is at hand
     * (bulk zips, e-mail attachments). The host allowlist is still enforced. Version-specific
     * downloads go through {@see self::downloadDocument()}.
     */
    public static function downloadByUrl(string $connectionName, string $url): string
    {
        $connection = Zgw::connection($connectionName);
        $connection->assertUrlAllowed($url);

        return $connection->request()->get($url)->body();
    }

    /**
     * Fetch a specific version of an enkelvoudiginformatieobject as a decoded array,
     * deriving the uuid like {@see self::byUrl()}.
     *
     * Uses the client's `show()` query parameters (laravel-zgw-client v1.1.0+) instead of
     * concatenating `?versie=` onto the URL by hand.
     *
     * @return array<string, mixed>
     */
    public static function showDocumentVersion(string $connectionName, string $uuid, int $versie): array
    {
        return self::ensureUuid(
            Zgw::connection($connectionName)
                ->documenten()
                ->enkelvoudiginformatieobjecten()
                ->show($uuid, ['versie' => $versie])
        );
    }

    /**
     * Download the binary content of an enkelvoudiginformatieobject, optionally a specific
     * version.
     *
     * Uses the client's `download()` `versie` query parameter (laravel-zgw-client v1.1.0+),
     * which targets the connection's own documenten download endpoint, so no manual host
     * allowlist check is needed.
     */
    public static function downloadDocument(string $connectionName, string $uuid, ?int $versie = null): string
    {
        return Zgw::connection($connectionName)
            ->documenten()
            ->enkelvoudiginformatieobjecten()
            ->download($uuid, $versie !== null ? ['versie' => $versie] : []);
    }

    /**
     * Ensure a single-resource array carries a uuid derived from its url segment.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function ensureUuid(array $data): array
    {
        if (! isset($data['uuid']) && isset($data['url']) && is_string($data['url'])) {
            $data['uuid'] = substr($data['url'], strrpos($data['url'], '/') + 1);
        }

        return $data;
    }
}
