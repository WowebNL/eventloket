<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves which ZGW connection (a name usable with Zgw::connection($name)) a given
 * piece of work belongs to.
 *
 * A Zaak and a Zaaktype both belong to a Municipality; almost every ZGW call site has
 * one of those in scope. When a municipality has its own connection configured it is
 * registered into the runtime config and selected by name, otherwise we fall back to
 * the global "main" connection from config/zgw.php.
 *
 * Registered as a container singleton so the per-municipality memo lives for the whole
 * request/worker lifetime (register-once semantics).
 */
class ZgwConnectionResolver
{
    public const DEFAULT_CONNECTION = 'main';

    /**
     * Cache key for the host => municipality_id index used by forUrl(). The
     * MunicipalityZgwConnectionObserver forgets it whenever a connection changes.
     */
    public const HOST_INDEX_CACHE_KEY = 'zgw_connection_host_index';

    /**
     * Memo of municipality id => resolved connection name.
     *
     * @var array<int, string>
     */
    private array $resolved = [];

    /**
     * Resolve the connection name for any context that carries a municipality.
     */
    public function for(Municipality|Zaak|Zaaktype|null $context): string
    {
        return match (true) {
            $context instanceof Municipality => $this->forMunicipality($context),
            $context instanceof Zaak => $this->forMunicipality($context->municipality),
            $context instanceof Zaaktype => $this->forMunicipality($context->municipality),
            default => self::DEFAULT_CONNECTION,
        };
    }

    /**
     * Resolve the connection name for a municipality, falling back to "main".
     *
     * Per-municipality DB-backed connections are wired in a later step; for now every
     * municipality maps to the global "main" connection.
     */
    public function forMunicipality(?Municipality $municipality): string
    {
        if ($municipality === null) {
            return self::DEFAULT_CONNECTION;
        }

        return $this->resolved[$municipality->id] ??= $this->resolve($municipality);
    }

    /**
     * Whether a connection is one we manage ourselves and may write to.
     *
     * Only our own OpenZaak (the application's default connection) is writable
     * by the setup commands. Per-municipality connections ("gemeente_{id}") are
     * externally managed instances: the commands validate them read-only and
     * never create, patch or publish there.
     */
    public function isManaged(string $connectionName): bool
    {
        return $connectionName === config('zgw.default', self::DEFAULT_CONNECTION);
    }

    /**
     * Resolve the connection name for an incoming ZGW resource URL (webhook path).
     *
     * The exact local Zaak lookup is the most reliable signal: a zaak URL maps to exactly
     * one municipality. When that fails (e.g. a document notification whose object is not a
     * zaak), fall back to matching the URL host against a per-municipality connection that
     * owns that host uniquely. A host shared with the main connection (or with more than one
     * municipality) is ambiguous and resolves to "main".
     */
    public function forUrl(string $zgwUrl): string
    {
        $zaak = Zaak::query()->where('zgw_zaak_url', $zgwUrl)->first();

        if ($zaak !== null) {
            return $this->for($zaak);
        }

        $host = $this->host($zgwUrl);
        $municipalityId = $host === null ? null : ($this->hostIndex()[$host] ?? null);

        if ($municipalityId !== null) {
            return $this->forMunicipality(Municipality::find($municipalityId));
        }

        return self::DEFAULT_CONNECTION;
    }

    /**
     * Resolve and register the connection for a municipality.
     *
     * When the municipality has its own connection, its config is registered
     * once into the runtime config under "gemeente_{id}" (the ZgwManager reads
     * config lazily per connection() call, so this takes effect immediately).
     * An invalid config (e.g. a weak secret) is logged and falls back to "main".
     */
    private function resolve(Municipality $municipality): string
    {
        $connection = $municipality->zgwConnection;

        if ($connection === null) {
            return self::DEFAULT_CONNECTION;
        }

        $name = "gemeente_{$municipality->id}";

        try {
            config(["zgw.connections.{$name}" => $connection->buildConfig()]);
        } catch (Throwable $e) {
            Log::warning('ZGW connection for municipality is invalid, falling back to main.', [
                'municipality_id' => $municipality->id,
                'exception' => $e->getMessage(),
            ]);

            return self::DEFAULT_CONNECTION;
        }

        return $name;
    }

    /**
     * Map every host that uniquely identifies a single per-municipality
     * connection to that municipality's id. Hosts shared with the main
     * connection or with more than one municipality are left out (ambiguous).
     *
     * Cached forever and invalidated by the connection observer.
     *
     * @return array<string, int>
     */
    private function hostIndex(): array
    {
        return Cache::rememberForever(self::HOST_INDEX_CACHE_KEY, function (): array {
            /** @var array<string, array<int|string, true>> $hostOwners */
            $hostOwners = [];

            foreach ($this->configHosts((array) config('zgw.connections.main', [])) as $host) {
                $hostOwners[$host]['main'] = true;
            }

            foreach (MunicipalityZgwConnection::query()->get() as $connection) {
                foreach ($this->connectionHosts($connection) as $host) {
                    $hostOwners[$host][$connection->municipality_id] = true;
                }
            }

            $index = [];

            foreach ($hostOwners as $host => $owners) {
                if (count($owners) !== 1) {
                    continue;
                }

                $owner = array_key_first($owners);

                if ($owner !== 'main') {
                    $index[$host] = (int) $owner;
                }
            }

            return $index;
        });
    }

    /**
     * The distinct hosts explicitly configured on a per-municipality connection
     * row (its six base URLs plus any allowed_hosts). Inherited (null) URLs are
     * ignored: they resolve to the main host, which is intentionally ambiguous.
     *
     * @return list<string>
     */
    private function connectionHosts(MunicipalityZgwConnection $connection): array
    {
        $candidates = [
            $connection->zaken_url,
            $connection->catalogi_url,
            $connection->documenten_url,
            $connection->besluiten_url,
            $connection->autorisaties_url,
            $connection->notificaties_url,
            ...($connection->allowed_hosts ?? []),
        ];

        return $this->hostsFrom($candidates);
    }

    /**
     * The distinct hosts of the main connection config (its URLs and allowed_hosts).
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function configHosts(array $config): array
    {
        $urls = is_array($config['urls'] ?? null) ? array_values($config['urls']) : [];
        $allowed = is_array($config['allowed_hosts'] ?? null) ? array_values($config['allowed_hosts']) : [];

        return $this->hostsFrom([...$urls, ...$allowed]);
    }

    /**
     * Reduce a list of URLs (or bare hosts) to their distinct lowercase hosts.
     *
     * @param  array<int, mixed>  $candidates
     * @return list<string>
     */
    private function hostsFrom(array $candidates): array
    {
        $hosts = [];

        foreach ($candidates as $candidate) {
            $host = $this->host(is_string($candidate) ? $candidate : '');

            if ($host !== null) {
                $hosts[$host] = true;
            }
        }

        return array_keys($hosts);
    }

    /**
     * Parse the lowercase host from a URL, or from a bare "host[:port]" value.
     */
    private function host(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) && ! str_contains($value, '://')) {
            $host = parse_url('https://'.$value, PHP_URL_HOST);
        }

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
