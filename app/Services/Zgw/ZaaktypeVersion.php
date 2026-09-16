<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Woweb\Zgw\Facades\Zgw;

/**
 * Resolves the current definitief version of a zaaktype identificatie in a
 * connection's catalogus: the version valid today, falling back to any
 * definitief version. Mirrors the resolution used by
 * {@see ZaaktypeCatalogusOptions} and zaak creation.
 */
final class ZaaktypeVersion
{
    /**
     * Null means the catalogus read succeeded but no definitief version exists
     * (deleted or never published). Transport errors bubble to the caller so it
     * can distinguish "gone" from "unreachable".
     *
     * @return array<string, mixed>|null
     */
    public static function currentDefinitief(string $connectionName, string $identificatie): ?array
    {
        $version = Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
            'identificatie' => $identificatie,
            'status' => 'definitief',
            'datumGeldigheid' => now('Europe/Amsterdam')->toDateString(),
        ])->first()
            ?? Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
                'identificatie' => $identificatie,
                'status' => 'definitief',
            ])->first();

        if (! is_array($version) || ! is_string($version['url'] ?? null) || $version['url'] === '') {
            return null;
        }

        return $version;
    }
}
