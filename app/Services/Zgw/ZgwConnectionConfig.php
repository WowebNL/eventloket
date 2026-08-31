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
     * The roles whose document visibility no connection may narrow: they span
     * every municipality and every connection, so they see the full
     * vertrouwelijkheid scale by definition rather than by configuration.
     *
     * @var array<int, Role>
     */
    private const UNRESTRICTED_ROLES = [
        Role::Admin,
        Role::KoppelingBeheerder,
    ];

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
     * connection.
     *
     * Three regimes, deliberately kept apart:
     *
     * - The platform-wide roles see the full scale on every connection. Their
     *   visibility is not configurable (they are not part of the connection
     *   form's role groups, see {@see DocumentAudience::groups()}), and reading
     *   them as unconfigured would drop them onto the legacy defaults below,
     *   which exclude openbaar, beperkt_openbaar and intern. A backend that
     *   registers a document at one of those levels would then hide it from the
     *   very roles that exist to see everything.
     * - A connection without a map for this role falls back to the hardcoded
     *   {@see DocumentVertrouwelijkheden::fromUserRole()} sets. Those are the
     *   legacy three-step defaults and are used verbatim, so the default
     *   connection behaves exactly as it always has.
     * - A connection whose map configures this role stores a single maximum
     *   vertrouwelijkheidaanduiding, which the standard defines as inclusive
     *   over the ordered scale. The set is derived from it with
     *   {@see DocumentVertrouwelijkheden::atMost()}.
     *
     * Either way the answer is a set of levels, so every consumer of the
     * role-based filtering stays unchanged.
     *
     * @return array<int, string>
     */
    public static function documentVisibilityForRole(string $connectionName, Role $role): array
    {
        if (in_array($role, self::UNRESTRICTED_ROLES, true)) {
            return DocumentVertrouwelijkheden::order();
        }

        $max = self::documentVisibilityMaxForRole($connectionName, $role);

        if ($max !== null) {
            return DocumentVertrouwelijkheden::atMost($max);
        }

        return DocumentVertrouwelijkheden::fromUserRole($role);
    }

    /**
     * The maximum vertrouwelijkheidaanduiding this connection's map allows the
     * given role to see, or null when the role is not configured (and the
     * hardcoded defaults apply).
     */
    public static function documentVisibilityMaxForRole(string $connectionName, Role $role): ?string
    {
        $visibility = config("zgw.connections.{$connectionName}.vertrouwelijkheid_map.visibility");

        if (! is_array($visibility) || ! isset($visibility[$role->value])) {
            return null;
        }

        return self::readVisibilityMax($visibility[$role->value]);
    }

    /**
     * Read a stored visibility entry as a single maximum level.
     *
     * The current form stores one level per role group. Maps written before the
     * maximum was introduced stored the full set of visible levels; such a set is
     * read as its most confidential member, which is the maximum it expressed.
     * Anything else (an empty set, an unknown level) reads as "not configured".
     */
    public static function readVisibilityMax(mixed $stored): ?string
    {
        if (is_string($stored)) {
            return DocumentVertrouwelijkheden::tryFrom($stored)?->value;
        }

        if (is_array($stored)) {
            return DocumentVertrouwelijkheden::mostConfidential($stored);
        }

        return null;
    }

    /**
     * The distinct maximum levels this connection's map configures across its
     * role groups: the levels that actually separate one role group from the
     * next, ordered from the least to the most confidential.
     *
     * These are the rungs the upload choice can offer. Any level in between is
     * seen by exactly the same role groups as the maximum just above it, so it
     * would collapse into that rung anyway.
     *
     * Empty when the connection has no visibility map, in which case the caller
     * falls back to the fixed {@see DocumentVertrouwelijkheden::uploadChoices()}.
     *
     * @return array<int, string>
     */
    public static function configuredVisibilityMaxLevels(string $connectionName): array
    {
        $visibility = config("zgw.connections.{$connectionName}.vertrouwelijkheid_map.visibility");

        if (! is_array($visibility)) {
            return [];
        }

        $maxima = [];

        foreach ($visibility as $stored) {
            $max = self::readVisibilityMax($stored);

            if ($max !== null) {
                $maxima[$max] = true;
            }
        }

        return array_values(array_filter(
            DocumentVertrouwelijkheden::order(),
            static fn (string $level): bool => isset($maxima[$level]),
        ));
    }

    /**
     * The default vertrouwelijkheidaanduiding applied when a user of the given
     * role uploads a document without choosing one. Falls back to the legacy
     * behaviour (the organiser gets zaakvertrouwelijk, everyone else
     * vertrouwelijk).
     *
     * One exception, and it is deliberately narrow. An organiser never gets the
     * choice select, so this default carries all of their uploads. On a
     * connection that configures a maximum for the organiser, `openbaar` is by
     * construction at or below every role group's maximum and therefore visible
     * to all of them, which is what an organiser upload is meant to be, while
     * `zaakvertrouwelijk` may sit above the maxima and hide the document from
     * everyone. Without such a maximum the visibility falls back to the legacy
     * sets, which do not contain `openbaar` at all: defaulting to it there would
     * produce exactly the invisible upload it is meant to prevent, so the legacy
     * `zaakvertrouwelijk` stands.
     */
    public static function uploadDefaultForRole(string $connectionName, Role $role): string
    {
        $defaults = config("zgw.connections.{$connectionName}.vertrouwelijkheid_map.upload_default");

        if (is_array($defaults) && isset($defaults[$role->value]) && is_string($defaults[$role->value]) && $defaults[$role->value] !== '') {
            return $defaults[$role->value];
        }

        if ($role === Role::Organiser && self::documentVisibilityMaxForRole($connectionName, $role) !== null) {
            return DocumentVertrouwelijkheden::Openbaar->value;
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

    /**
     * Whether an organiser may withdraw a zaak on this connection. Defaults to
     * true, so the global "main" connection (and any connection without the key)
     * keeps withdrawal enabled. Always disabled for a OneGround (RX Mission)
     * backend, where setting the eind-status archives the zaak immediately (and
     * OneGround rejects that unless all documents are already 'gearchiveerd').
     */
    public static function allowsOrganiserWithdrawal(string $connectionName): bool
    {
        if (self::isOneGround($connectionName)) {
            return false;
        }

        $value = config("zgw.connections.{$connectionName}.allow_organiser_withdrawal");

        return $value === null ? true : (bool) $value;
    }

    /**
     * Whether this is the global "main" connection from config/zgw.php: our own
     * OpenZaak instance, the one every zaak used before municipalities could
     * bring their own. Any other name belongs to a municipality-owned instance.
     *
     * Deliberately not expressed as "not OneGround": a future OpenZaak
     * connection of a municipality is still someone else's instance, and
     * behaviour we only kept for backwards compatibility with our own history
     * should not leak into it.
     */
    public static function isDefaultConnection(string $connectionName): bool
    {
        return $connectionName === ZgwConnectionResolver::DEFAULT_CONNECTION;
    }

    /**
     * Whether this connection talks to a OneGround (RX Mission) backend, which
     * deviates from the ZGW standard on a few points (bare-string overigeData,
     * eager archiving on eind-status). Defaults to false so the global "main"
     * connection and any OpenZaak connection keep standard behaviour.
     */
    public static function isOneGround(string $connectionName): bool
    {
        return (bool) config("zgw.connections.{$connectionName}.is_oneground");
    }
}
