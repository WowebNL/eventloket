<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads the application-level parameters of a ZGW connection (the keys under
 * config('zgw.connections.{name}') that the package itself ignores) and applies
 * the per-connection wire-format conventions. These differ per ZGW instance and
 * default to the current OpenZaak behaviour.
 */
class ZgwConnectionConfig
{
    /**
     * Apply the optional eigenschap date format to a scalar zaakeigenschap value.
     *
     * When the connection sets `eigenschap_date_format` (e.g. 'YmdHis' for RX
     * Mission) and the value parses as a date, it is reformatted. Otherwise the
     * value is returned unchanged, which is the legacy OpenZaak behaviour.
     */
    public static function formatEigenschapWaarde(string $connectionName, string $waarde): string
    {
        $format = config("zgw.connections.{$connectionName}.eigenschap_date_format");

        if (! is_string($format) || $format === '' || $waarde === '') {
            return $waarde;
        }

        try {
            return CarbonImmutable::parse($waarde)->format($format);
        } catch (Throwable) {
            return $waarde;
        }
    }
}
