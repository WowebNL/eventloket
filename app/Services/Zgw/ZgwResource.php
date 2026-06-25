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
