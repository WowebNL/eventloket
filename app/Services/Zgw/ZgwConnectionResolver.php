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
     * Cache key for the set of trusted ZGW hosts used to validate notification
     * webhook URLs. Forgotten by the observer whenever a connection changes.
     */
    public const ALLOWED_HOSTS_CACHE_KEY = 'zgw_allowed_notification_hosts';

    /**
     * Memo of municipality id => resolved connection name (runtime path, gated
     * on the connection being activated).
     *
     * @var array<int, string>
     */
    private array $resolved = [];

    /**
     * Memo of municipality id => resolved connection name (management path, not
     * gated on activation).
     *
     * @var array<int, string>
     */
    private array $resolvedForManagement = [];

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
     * Resolve the connection name for a municipality on the runtime path (form
     * submissions and everything acting on a zaak), falling back to "main".
     *
     * This path is gated on activation: a configured but not-yet-activated
     * connection is treated as absent so submissions keep going to "main" until
     * the municipality goes live. Management surfaces that need to read the
     * connection's catalogi while configuring it (before activation) must use
     * {@see forManagement()} instead.
     */
    public function forMunicipality(?Municipality $municipality): string
    {
        if ($municipality === null) {
            return self::DEFAULT_CONNECTION;
        }

        return $this->resolved[$municipality->id] ??= $this->resolve($municipality);
    }

    /**
     * Resolve the connection name for a municipality on the management path
     * (configuring the zaaktype koppeling, syncing zaaktypen, reading catalogi),
     * falling back to "main".
     *
     * Unlike {@see forMunicipality()} this is not gated on activation: a
     * connection is used as soon as it exists, so a municipality can set up and
     * verify its whole koppeling before it goes live. Activation only decides
     * whether the runtime path uses the connection for submissions.
     */
    public function forManagement(?Municipality $municipality): string
    {
        if ($municipality === null) {
            return self::DEFAULT_CONNECTION;
        }

        return $this->resolvedForManagement[$municipality->id] ??= $this->register($municipality);
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
     * Every host that belongs to a trusted ZGW connection: the main connection's
     * URLs and allowed_hosts, the legacy OpenZaak host, and each activated
     * per-municipality connection's explicit URLs and allowed_hosts. An inactive
     * connection processes no real zaken yet, so its host is not trusted until it
     * is activated. The notification webhook
     * accepts a notification whose URLs point at any of these hosts, so a
     * municipality with its own ZGW host is no longer rejected, while unknown or
     * internal hosts still are.
     *
     * Cached forever and invalidated by the connection observer.
     *
     * @return list<string>
     */
    public function allowedNotificationHosts(): array
    {
        return Cache::rememberForever(self::ALLOWED_HOSTS_CACHE_KEY, function (): array {
            $hosts = [];

            foreach ($this->configHosts((array) config('zgw.connections.main', [])) as $host) {
                $hosts[$host] = true;
            }

            $legacy = $this->host((string) config('openzaak.url', ''));
            if ($legacy !== null) {
                $hosts[$legacy] = true;
            }

            foreach (MunicipalityZgwConnection::query()->active()->get() as $connection) {
                foreach ($this->connectionHosts($connection) as $host) {
                    $hosts[$host] = true;
                }
            }

            return array_keys($hosts);
        });
    }

    /**
     * Resolve and register the connection for a municipality on the runtime path.
     *
     * A connection that exists but has not been activated is ignored and the
     * municipality routes to "main", exactly as if it had no own connection, so
     * form submissions only reach the municipality's ZGW once it is live.
     */
    private function resolve(Municipality $municipality): string
    {
        $connection = $municipality->zgwConnection;

        if ($connection === null || ! $connection->isActive()) {
            return self::DEFAULT_CONNECTION;
        }

        return $this->register($municipality);
    }

    /**
     * Register the municipality's connection config and return its name,
     * regardless of activation.
     *
     * The config is registered once into the runtime config under "gemeente_{id}"
     * (the ZgwManager reads config lazily per connection() call, so this takes
     * effect immediately). Returns "main" when the municipality has no own
     * connection, or when its config is invalid (e.g. a weak secret), which is
     * logged.
     */
    private function register(Municipality $municipality): string
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
     * Map every host that uniquely identifies a single activated per-municipality
     * connection to that municipality's id. Inactive connections are excluded.
     * Hosts shared with the main connection or with more than one municipality
     * are left out (ambiguous).
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

            foreach (MunicipalityZgwConnection::query()->active()->get() as $connection) {
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
