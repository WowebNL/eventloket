<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Illuminate\Support\Facades\Cache;
use Throwable;
use Woweb\Zgw\Facades\Zgw;

/**
 * Builds the option lists for the per-municipality zaaktype blueprint UI by
 * reading the live catalogi of a connection. Every list is keyed by the stable
 * label the {@see ZaaktypeBlueprint} matches on (the eigenschap naam, the
 * statustype/roltype/resultaattype omschrijving, the informatieobjecttype
 * omschrijving), so a selected value resolves against a future zaaktype version.
 *
 * Reads are cached briefly per connection so a reactive Filament form does not
 * hammer the API on every render, while still reflecting catalogus edits soon.
 * A failing read degrades to an empty list rather than breaking the form.
 */
final class ZaaktypeCatalogusOptions
{
    private const TTL_SECONDS = 300;

    /**
     * The definitief zaaktypen of the connection, one entry per identificatie.
     *
     * @return array<string, string> identificatie => "identificatie — omschrijving"
     */
    public static function zaaktypen(string $connectionName): array
    {
        return self::remember($connectionName, 'zaaktypen', '', function () use ($connectionName): array {
            $options = [];

            foreach (Zgw::connection($connectionName)->catalogi()->zaaktypen()->index(['status' => 'definitief']) as $zaaktype) {
                $identificatie = $zaaktype['identificatie'] ?? null;

                if (! is_string($identificatie) || $identificatie === '' || isset($options[$identificatie])) {
                    continue;
                }

                $omschrijving = (string) ($zaaktype['omschrijving'] ?? $identificatie);
                $options[$identificatie] = trim("{$identificatie} — {$omschrijving}");
            }

            return $options;
        });
    }

    /**
     * The eigenschap namen of the identificatie's current definitief version.
     *
     * @return array<string, string> naam => naam
     */
    public static function eigenschappen(string $connectionName, string $identificatie): array
    {
        return self::forZaaktype($connectionName, $identificatie, 'eigenschappen', function (string $url) use ($connectionName): array {
            $options = [];

            foreach (Zgw::connection($connectionName)->catalogi()->eigenschappen()->index(['zaaktype' => $url]) as $eigenschap) {
                $naam = $eigenschap['naam'] ?? null;

                if (is_string($naam) && $naam !== '') {
                    $options[$naam] = $naam;
                }
            }

            return $options;
        });
    }

    /**
     * @return array<string, string> omschrijving => "volgnummer. omschrijving"
     */
    public static function statustypen(string $connectionName, string $identificatie): array
    {
        return self::forZaaktype($connectionName, $identificatie, 'statustypen', function (string $url) use ($connectionName): array {
            $options = [];

            foreach (Zgw::connection($connectionName)->catalogi()->statustypen()->index(['zaaktype' => $url]) as $statustype) {
                $omschrijving = $statustype['omschrijving'] ?? null;

                if (is_string($omschrijving) && $omschrijving !== '') {
                    $volgnummer = $statustype['volgnummer'] ?? null;
                    $options[$omschrijving] = $volgnummer !== null ? "{$volgnummer}. {$omschrijving}" : $omschrijving;
                }
            }

            return $options;
        });
    }

    /**
     * @return array<string, string> omschrijving => label
     */
    public static function roltypen(string $connectionName, string $identificatie): array
    {
        return self::forZaaktype($connectionName, $identificatie, 'roltypen', function (string $url) use ($connectionName): array {
            return self::labelledByOmschrijving(
                Zgw::connection($connectionName)->catalogi()->roltypen()->index(['zaaktype' => $url]),
            );
        });
    }

    /**
     * @return array<string, string> omschrijving => label
     */
    public static function resultaattypen(string $connectionName, string $identificatie): array
    {
        return self::forZaaktype($connectionName, $identificatie, 'resultaattypen', function (string $url) use ($connectionName): array {
            return self::labelledByOmschrijving(
                Zgw::connection($connectionName)->catalogi()->resultaattypen()->index(['zaaktype' => $url]),
            );
        });
    }

    /**
     * The informatieobjecttypen linked to the zaaktype via the standard relation.
     *
     * @return array<string, string> omschrijving => omschrijving
     */
    public static function informatieobjecttypen(string $connectionName, string $identificatie): array
    {
        return self::forZaaktype($connectionName, $identificatie, 'informatieobjecttypen', function (string $url) use ($connectionName): array {
            $options = [];

            $relations = Zgw::connection($connectionName)->catalogi()->zaaktypeInformatieobjecttypen()->index(['zaaktype' => $url]);

            foreach ($relations as $relation) {
                $typeUrl = $relation['informatieobjecttype'] ?? null;

                if (! is_string($typeUrl) || $typeUrl === '') {
                    continue;
                }

                $type = ZgwResource::byUrl($connectionName, $typeUrl);
                $omschrijving = $type['omschrijving'] ?? null;

                if (is_string($omschrijving) && $omschrijving !== '') {
                    $options[$omschrijving] = $omschrijving;
                }
            }

            return $options;
        });
    }

    /**
     * Resolve the current definitief version url for an identificatie, then run
     * the builder against it. Returns an empty list when no version is found.
     *
     * @param  callable(string): array<string, string>  $builder
     * @return array<string, string>
     */
    private static function forZaaktype(string $connectionName, string $identificatie, string $resource, callable $builder): array
    {
        if ($identificatie === '') {
            return [];
        }

        return self::remember($connectionName, $resource, $identificatie, function () use ($connectionName, $identificatie, $builder): array {
            $url = self::versionUrl($connectionName, $identificatie);

            return $url === null ? [] : $builder($url);
        });
    }

    private static function versionUrl(string $connectionName, string $identificatie): ?string
    {
        $version = Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
            'identificatie' => $identificatie,
            'status' => 'definitief',
        ])->first();

        $url = $version['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Key a catalogi list by omschrijving, labelling with omschrijvingGeneriek
     * when it differs (so two entries that share an omschrijving are still
     * distinguishable to the user).
     *
     * @param  iterable<array<string, mixed>>  $items
     * @return array<string, string>
     */
    private static function labelledByOmschrijving(iterable $items): array
    {
        $options = [];

        foreach ($items as $item) {
            $omschrijving = $item['omschrijving'] ?? null;

            if (! is_string($omschrijving) || $omschrijving === '') {
                continue;
            }

            $generiek = $item['omschrijvingGeneriek'] ?? null;
            $options[$omschrijving] = is_string($generiek) && $generiek !== '' && $generiek !== $omschrijving
                ? "{$omschrijving} ({$generiek})"
                : $omschrijving;
        }

        return $options;
    }

    /**
     * Cache a catalogi read, degrading to an empty list on any failure.
     *
     * @param  callable(): array<string, string>  $builder
     * @return array<string, string>
     */
    private static function remember(string $connectionName, string $resource, string $discriminator, callable $builder): array
    {
        $key = 'zaaktype_catalogus_options.'.md5($connectionName.'|'.$resource.'|'.$discriminator);

        try {
            return Cache::remember($key, self::TTL_SECONDS, $builder);
        } catch (Throwable) {
            return [];
        }
    }
}
