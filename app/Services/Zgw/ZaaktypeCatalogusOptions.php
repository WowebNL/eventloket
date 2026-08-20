<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
     * The resultaattypen of the identificatie's current definitief version, keyed
     * by their url. Used by the per-municipality "hide results" picker, whose
     * stored values are resultaattype urls (the stable value the calendar filter
     * compares against, mirroring the admin-level hidden_resultaat_types).
     *
     * @return array<string, string> url => omschrijving
     */
    public static function resultaattypenByUrl(string $connectionName, string $identificatie): array
    {
        return self::forZaaktype($connectionName, $identificatie, 'resultaattypen_by_url', function (string $url) use ($connectionName): array {
            $options = [];

            foreach (Zgw::connection($connectionName)->catalogi()->resultaattypen()->index(['zaaktype' => $url]) as $resultaattype) {
                $value = $resultaattype['url'] ?? null;
                $omschrijving = $resultaattype['omschrijving'] ?? null;

                if (is_string($value) && $value !== '' && is_string($omschrijving) && $omschrijving !== '') {
                    $options[$value] = $omschrijving;
                }
            }

            return $options;
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
                $value = $relation['informatieobjecttype'] ?? null;

                if (! is_string($value) || $value === '') {
                    continue;
                }

                // OpenZaak returns a URL to the informatieobjecttype; RX Mission
                // returns the omschrijving inline. Fetch the omschrijving for a
                // URL, otherwise use the value as the omschrijving directly.
                if (str_starts_with($value, 'http')) {
                    // A single unreadable type (e.g. a 404 or a host outside the
                    // allowlist) must not wipe the whole list; skip it.
                    try {
                        $omschrijving = ZgwResource::byUrl($connectionName, $value)['omschrijving'] ?? null;
                    } catch (Throwable $e) {
                        Log::warning('ZaaktypeCatalogusOptions: kon informatieobjecttype niet ophalen', [
                            'connection' => $connectionName,
                            'informatieobjecttype' => $value,
                            'exception' => $e->getMessage(),
                        ]);

                        continue;
                    }
                } else {
                    $omschrijving = $value;
                }

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

    /**
     * Resolve (and cache) the current definitief version url for an identificatie.
     *
     * The same version url is needed by every dependent option list
     * (eigenschappen, statustypen, roltypen, …). Caching it here means a form
     * that renders several of those selects resolves the version once instead
     * of once per resource type.
     */
    private static function versionUrl(string $connectionName, string $identificatie): ?string
    {
        $resolved = self::remember($connectionName, 'version_url', $identificatie, function () use ($connectionName, $identificatie): array {
            // An identificatie can have several definitief versions; only the one
            // valid today carries the eigenschappen and relations we want, so filter
            // on datumGeldigheid. Fall back to any definitief version when none is
            // marked valid today.
            $version = Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
                'identificatie' => $identificatie,
                'status' => 'definitief',
                'datumGeldigheid' => now('Europe/Amsterdam')->toDateString(),
            ])->first()
                ?? Zgw::connection($connectionName)->catalogi()->zaaktypen()->index([
                    'identificatie' => $identificatie,
                    'status' => 'definitief',
                ])->first();

            $url = $version['url'] ?? null;

            return is_string($url) && $url !== '' ? ['url' => $url] : [];
        });

        return $resolved['url'] ?? null;
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
        } catch (Throwable $e) {
            // Degrade to an empty list rather than breaking the form, but log the
            // cause so a failing catalogi read on an external ZGW backend is
            // diagnosable instead of looking like an empty catalogus.
            Log::warning('ZaaktypeCatalogusOptions: kon catalogi-opties niet ophalen', [
                'connection' => $connectionName,
                'resource' => $resource,
                'discriminator' => $discriminator,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
