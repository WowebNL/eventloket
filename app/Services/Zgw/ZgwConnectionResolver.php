<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
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
     * Resolve the connection name for an incoming ZGW resource URL (webhook path).
     *
     * The exact local Zaak lookup is the most reliable signal: a zaak URL maps to exactly
     * one municipality. Host-based matching against configured connections is added when
     * per-municipality connections exist; until then everything else falls back to "main".
     */
    public function forUrl(string $zgwUrl): string
    {
        $zaak = Zaak::query()->where('zgw_zaak_url', $zgwUrl)->first();

        if ($zaak !== null) {
            return $this->for($zaak);
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
}
