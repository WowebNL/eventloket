<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Woweb\Zgw\Exceptions\ApiRequestException;
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
     * The option lists that describe one zaaktype identificatie. They all hang
     * off its current definitief version, so republishing that version makes
     * every one of them stale at the same moment.
     */
    private const ZAAKTYPE_RESOURCES = [
        'eigenschappen',
        'statustypen',
        'roltypen',
        'resultaattypen',
        'resultaattypen_by_url',
        'informatieobjecttypen',
    ];

    /**
     * The response statuses that confirm a resource is really gone, as opposed
     * to temporarily unreadable.
     *
     * Deliberately not the same set as the statuses the notification handling
     * skips on: that list also treats a 401 and a 403 as a reason to move on,
     * and those say something about our credentials rather than about whether
     * the resource still exists. A 410 is the other way round, an answer about
     * the resource that the notification side has no use for. The two sets
     * therefore overlap on 404 rather than one containing the other.
     */
    private const GONE_STATUSES = [404, 410];

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
     * Reconcile stored resultaattype urls with the identificatie's current
     * definitief version.
     *
     * A resultaattype url identifies one resultaattype of one zaaktype version,
     * so republishing a zaaktype gives every resultaattype a new url while the
     * omschrijving stays the same. A selection stored against the previous
     * version then names urls the current version does not have, which is not
     * only wrong for the runtime comparison: it also falls outside the options
     * the picker offers, so the form refuses to save until the selection is
     * cleared by hand.
     *
     * The stored urls are therefore matched onto the current version: a url the
     * version still has is kept as is, and one it no longer has is followed to
     * read its omschrijving and re-pointed at the current resultaattype with
     * that omschrijving.
     *
     * Dropping a url takes evidence, because a dropped url is gone for good
     * once the koppeling is saved. Only two answers are evidence: the backend
     * confirming the url is gone, and a successful read whose omschrijving the
     * current version no longer offers. Anything else, an unreadable list or an
     * unreadable single url, leaves the stored selection alone.
     *
     * @param  array<int|string, mixed>  $stored
     * @return array<int, string>
     */
    public static function reconcileResultaattypeUrls(string $connectionName, string $identificatie, array $stored): array
    {
        if ($stored === []) {
            return [];
        }

        $current = self::resultaattypenByUrl($connectionName, $identificatie);

        if ($current === []) {
            // Every catalogi read here degrades to an empty list on failure, so
            // "no resultaattypen" cannot be told apart from "could not read
            // them". Keep the selection rather than discard a configuration
            // over an unreachable backend.
            return array_values(array_filter($stored, 'is_string'));
        }

        $currentByOmschrijving = [];

        foreach ($current as $url => $omschrijving) {
            $currentByOmschrijving[$omschrijving] ??= $url;
        }

        $reconciled = [];

        foreach ($stored as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            if (isset($current[$url])) {
                $reconciled[$url] = true;

                continue;
            }

            $read = self::readResultaattype($connectionName, $url);

            if (! $read['readable']) {
                // The list read succeeded but this one url could not be read.
                // That says nothing about whether the resultaattype still
                // exists, so keep it: dropping it here would quietly and
                // permanently discard a configuration over a hiccup.
                $reconciled[$url] = true;

                continue;
            }

            $omschrijving = $read['omschrijving'];

            if ($omschrijving !== null && isset($currentByOmschrijving[$omschrijving])) {
                $reconciled[$currentByOmschrijving[$omschrijving]] = true;

                continue;
            }

            Log::info('ZaaktypeCatalogusOptions: opgeslagen resultaattype hoort niet meer bij de huidige zaaktypeversie', [
                'connection' => $connectionName,
                'identificatie' => $identificatie,
                'resultaattype' => $url,
            ]);
        }

        return array_keys($reconciled);
    }

    /**
     * Read a single resultaattype back by its url, reporting whether the answer
     * can be trusted as a statement about that resultaattype.
     *
     * `readable` is true when the backend answered about this url: either with
     * the resource (`omschrijving` filled) or with a confirmed "it is gone"
     * (`omschrijving` null). It is false for every other failure (a 5xx, a
     * timeout, a rejected credential), since none of those tell us whether the
     * resultaattype still exists. Only a readable answer may be acted on.
     *
     * A readable answer is cached like the option lists; a failed read is not,
     * so the next attempt reaches the backend again.
     *
     * @return array{readable: bool, omschrijving: string|null}
     */
    private static function readResultaattype(string $connectionName, string $url): array
    {
        $key = self::cacheKey($connectionName, 'resultaattype_read', $url);

        try {
            /** @var array{readable: bool, omschrijving: string|null} */
            return Cache::remember($key, self::TTL_SECONDS, function () use ($connectionName, $url): array {
                try {
                    $omschrijving = ZgwResource::byUrl($connectionName, $url)['omschrijving'] ?? null;
                } catch (Throwable $e) {
                    if (! self::confirmsGone($e)) {
                        // Not an answer about this url: let it out of the
                        // callback, so the failure is not cached as a result.
                        throw $e;
                    }

                    return ['readable' => true, 'omschrijving' => null];
                }

                return [
                    'readable' => true,
                    'omschrijving' => is_string($omschrijving) && $omschrijving !== '' ? $omschrijving : null,
                ];
            });
        } catch (Throwable $e) {
            Log::warning('ZaaktypeCatalogusOptions: kon resultaattype niet ophalen', [
                'connection' => $connectionName,
                'resultaattype' => $url,
                'exception' => $e->getMessage(),
            ]);

            return ['readable' => false, 'omschrijving' => null];
        }
    }

    /**
     * Whether an exception is the backend confirming the resource no longer
     * exists, rather than a transport or server error.
     */
    private static function confirmsGone(Throwable $e): bool
    {
        $status = match (true) {
            $e instanceof ApiRequestException => $e->getResponse()->status(),
            $e instanceof RequestException => $e->response->status(),
            default => null,
        };

        return in_array($status, self::GONE_STATUSES, true);
    }

    /**
     * Forget the cached option lists of one zaaktype identificatie, so the next
     * read reflects a version that has just been published. Reads keyed by a
     * version-specific url need no invalidation: a new version means new urls.
     */
    public static function forgetZaaktype(string $connectionName, string $identificatie): void
    {
        Cache::forget(self::cacheKey($connectionName, 'version_url', $identificatie));

        foreach (self::ZAAKTYPE_RESOURCES as $resource) {
            Cache::forget(self::cacheKey($connectionName, $resource, $identificatie));
        }
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
        $key = self::cacheKey($connectionName, $resource, $discriminator);

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

    private static function cacheKey(string $connectionName, string $resource, string $discriminator): string
    {
        return 'zaaktype_catalogus_options.'.md5($connectionName.'|'.$resource.'|'.$discriminator);
    }
}
