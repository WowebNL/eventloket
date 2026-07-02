<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
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
     * Format a scalar zaakeigenschap value for the wire.
     *
     * The catalogus eigenschap's formaat is authoritative: a `datum` wants
     * YYYYMMDD, a `datum_tijd` wants YYYYMMDDHHMMSS (which some backends such as
     * RX Mission enforce strictly, rejecting a bare date with a 400). Any other
     * formaat (tekst, getal, or an unknown/absent one) is sent unchanged, so a
     * text value that happens to parse as a date is never mangled.
     */
    public static function formatEigenschapWaarde(string $waarde, ?string $formaat = null): string
    {
        $format = match ($formaat) {
            'datum' => 'Ymd',
            'datum_tijd' => 'YmdHis',
            default => null,
        };

        if ($format === null || $waarde === '') {
            return $waarde;
        }

        try {
            return CarbonImmutable::parse($waarde)->format($format);
        } catch (Throwable) {
            return $waarde;
        }
    }

    /**
     * The RSIN to use as bronorganisatie / verantwoordelijkeOrganisatie on a
     * zaak for this connection. Falls back to main's value (which itself
     * defaults to the legacy Veiligheidsregio Zuid-Limburg RSIN).
     */
    public static function bronorganisatie(string $connectionName): string
    {
        $rsin = config("zgw.connections.{$connectionName}.bronorganisatie_rsin");

        if (is_string($rsin) && $rsin !== '') {
            return $rsin;
        }

        return (string) config('zgw.connections.main.bronorganisatie_rsin', '820151130');
    }

    /**
     * The vertrouwelijkheidaanduiding values a given role may see on this
     * connection. A connection without a configured map falls back to the
     * hardcoded {@see DocumentVertrouwelijkheden::fromUserRole()} defaults, so
     * the role-based filtering stays on either way.
     *
     * @return array<int, string>
     */
    public static function documentVisibilityForRole(string $connectionName, Role $role): array
    {
        $visibility = config("zgw.connections.{$connectionName}.vertrouwelijkheid_map.visibility");

        if (is_array($visibility) && isset($visibility[$role->value]) && is_array($visibility[$role->value])) {
            return array_values(array_map(strval(...), $visibility[$role->value]));
        }

        return DocumentVertrouwelijkheden::fromUserRole($role);
    }

    /**
     * The default vertrouwelijkheidaanduiding applied when a user of the given
     * role uploads a document without choosing one. Falls back to the legacy
     * behaviour (organiser = zaakvertrouwelijk, everyone else = vertrouwelijk).
     */
    public static function uploadDefaultForRole(string $connectionName, Role $role): string
    {
        $defaults = config("zgw.connections.{$connectionName}.vertrouwelijkheid_map.upload_default");

        if (is_array($defaults) && isset($defaults[$role->value]) && is_string($defaults[$role->value]) && $defaults[$role->value] !== '') {
            return $defaults[$role->value];
        }

        return match ($role) {
            Role::Organiser => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            default => DocumentVertrouwelijkheden::Vertrouwelijk->value,
        };
    }

    /**
     * The vertrouwelijkheidaanduiding for system-generated uploads (the
     * aanvraagformulier PDF and the organiser's form attachments). Falls back to
     * the legacy "zaakvertrouwelijk".
     */
    public static function systemUploadDefault(string $connectionName): string
    {
        $value = config("zgw.connections.{$connectionName}.vertrouwelijkheid_map.upload_default.system");

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return DocumentVertrouwelijkheden::Zaakvertrouwelijk->value;
    }
}
