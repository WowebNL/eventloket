<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\EventForm\State\FormState;
use App\Jobs\Submit\HashIdentifyingAttributes;
use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Jobs\Zaak\UpdateInitiatorZGW;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Builds the initiator rol payload from the form's initiator block. Shared by
 * {@see UpdateInitiatorZGW} (the hoofdzaak) and
 * {@see CreateDoorkomstZaken} (each doorkomst deelzaak), so a
 * deelzaak gets the same, properly filled aanvrager identification instead of a
 * copied ZGW rol whose betrokkeneIdentificatie is empty across instances.
 *
 * Variants, matching the aanvrager:
 * - has a KvK number, own default connection → niet_natuurlijk_persoon
 *   (statutaireNaam, annIdentificatie, kvkNummer)
 * - has a KvK number, any other connection   → vestiging (kvkNummer,
 *   handelsnaam)
 * - otherwise                                → natuurlijk_persoon (voornamen,
 *   geslachtsnaam, anpIdentificatie, verblijfsadres)
 *
 * A natuurlijk_persoon rol needs an identifying attribute for a ZGW backend to
 * materialise a betrokkene (OneGround/RX Mission shows nothing for a rol whose
 * betrokkeneIdentificatie only carries name parts). We have no BSN, so a stable
 * application-issued anpIdentificatie derived from the organiser user id is
 * sent, mirroring annIdentificatie on the organisation variant.
 */
final class InitiatorRolBuilder
{
    /**
     * ContactPersoonRol.naam is maxLength 40 in the Zaken API schema, unchanged
     * across every release this application targets, so a backend that
     * validates against the published schema rejects a longer composed name
     * (voornaam plus achternaam) with a 400 on the rol POST.
     */
    private const CONTACTPERSOON_NAAM_MAX_ONEGROUND = 40;

    /**
     * Our own OpenZaak stores this name in a 200 character column and accepts
     * up to that, so a connection to it keeps the wider bound instead of losing
     * name characters for a limit that backend does not apply. Anything longer
     * than 200 is still cut, because no backend accepts it.
     */
    private const CONTACTPERSOON_NAAM_MAX_DEFAULT = 200;

    // The remaining bounds the Zaken API schema puts on the parts of the rol
    // payload that carry organiser input. They are the same in every release
    // this application targets, so they are constants rather than a per-version
    // lookup. Every one of them is well below what the form accepts, so without
    // them a long answer reaches the API unbounded.
    private const EMAILADRES_MAX = 254;

    private const TELEFOONNUMMER_MAX = 20;

    private const GESLACHTSNAAM_MAX = 200;

    private const VOORNAMEN_MAX = 200;

    private const AFWIJKENDE_NAAM_MAX = 625;

    private const HANDELSNAAM_MAX = 625;

    private const STATUTAIRE_NAAM_MAX = 500;

    private const KVK_NUMMER_MAX = 8;

    private const WOONPLAATSNAAM_MAX = 80;

    private const OPENBARE_RUIMTE_NAAM_MAX = 80;

    private const POSTCODE_MAX = 7;

    private const HUISLETTER_MAX = 1;

    private const HUISNUMMERTOEVOEGING_MAX = 4;

    private const HUISNUMMER_MAX = 99999;

    /**
     * @param  string  $connectionName  the connection the rol is posted to, which decides
     *                                  which organisation variant is built
     * @param  array<string, mixed>  $initiator  output of ZaakeigenschappenMap::buildInitiator()
     * @return array<string, mixed>|null rol payload, or null when there is no initiator data
     */
    public static function build(string $connectionName, string $zaakUrl, string $roltype, FormState $state, array $initiator, ?string $anpIdentificatie = null): ?array
    {
        if ($initiator === []) {
            return null;
        }

        // OneGround enforces a stricter contactpersoonRol.naam limit than the
        // VNG standard, so the bound is decided per connection, not globally.
        $naamMax = ZgwConnectionConfig::isOneGround($connectionName)
            ? self::CONTACTPERSOON_NAAM_MAX_ONEGROUND
            : self::CONTACTPERSOON_NAAM_MAX_DEFAULT;

        if (! isset($initiator['kvk']) || ! $initiator['kvk']) {
            return self::natuurlijkPersoon($zaakUrl, $roltype, $state, $initiator, $anpIdentificatie, $naamMax);
        }

        $kvkNummer = self::kvkNummer($initiator['kvk']);

        return ZgwConnectionConfig::isDefaultConnection($connectionName)
            ? self::nietNatuurlijkPersoon($zaakUrl, $roltype, $initiator, $kvkNummer, $naamMax)
            : self::vestiging($zaakUrl, $roltype, $initiator, $kvkNummer, $naamMax);
    }

    /**
     * A free-text value cut to its schema bound, or null when there is nothing
     * to send. Cutting is the right degradation for a name: the value stays
     * recognisable, and the alternative is a 400 on the rol POST that aborts
     * the submit chain and leaves the zaak without an initiator. Values that
     * only mean something whole are dropped instead of cut; see
     * {@see self::kvkNummer()} and {@see self::verblijfsadres()}. A value at or
     * under its bound is left byte-for-byte as is.
     */
    private static function bounded(mixed $value, int $max): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Str::substr($value, 0, $max);
    }

    /**
     * The contactpersoonRol block for the rol, with every free-text field
     * bounded: naam to the limit that applies to this connection ($naamMax),
     * the contact details to their schema maxima. Shared by every
     * betrokkeneType variant, since contactpersoonRol travels with all of them,
     * so an organisation with a KvK number and a long contact name is bounded
     * just like a natuurlijk persoon. More of the name survives elsewhere
     * (afwijkendeNaamBetrokkene, geslachtsnaam plus voornamen), whose bounds
     * are far wider, so a plain cut to the hard limit is enough here.
     *
     * @param  array<string, mixed>  $initiator
     * @return array<string, mixed>|null
     */
    private static function contactpersoonRol(array $initiator, int $naamMax): ?array
    {
        $contactpersoon = $initiator['contactpersoon'] ?? null;

        if (! is_array($contactpersoon)) {
            return null;
        }

        $maxima = [
            'naam' => $naamMax,
            'emailadres' => self::EMAILADRES_MAX,
            'telefoonnummer' => self::TELEFOONNUMMER_MAX,
        ];

        foreach ($maxima as $field => $max) {
            if (isset($contactpersoon[$field]) && is_string($contactpersoon[$field])) {
                $contactpersoon[$field] = Str::substr($contactpersoon[$field], 0, $max);
            }
        }

        return $contactpersoon;
    }

    /**
     * The KvK number as it may be sent to ZGW, or null when the snapshot no
     * longer holds the plain value.
     *
     * The submit chain hashes the KvK number only after the doorkomst zaken are
     * created, so in that run the number is still readable. A rerun of a single
     * job (a retry, or `zaak:create-doorkomst-zaken` on an existing zaak) reads
     * the already-hashed snapshot, and writing that hash to ZGW as if it were a
     * KvK number would put a bogus company number on the zaak. The rol is then
     * registered on the organisation name alone (statutaireNaam or handelsnaam,
     * depending on the variant), so this guard covers annIdentificatie and
     * kvkNummer in both.
     *
     * A number that does not fit the schema bound is dropped for the same
     * reason and not cut: half a company number is a different company, so
     * sending it would be worse than sending none.
     */
    private static function kvkNummer(mixed $kvk): ?string
    {
        if (! is_string($kvk) || str_starts_with($kvk, HashIdentifyingAttributes::HASH_PREFIX)) {
            return null;
        }

        return Str::length($kvk) > self::KVK_NUMMER_MAX ? null : $kvk;
    }

    /**
     * Stable identifier for a private aanvrager, derived from the organiser
     * user id so the same person maps to the same betrokkene across zaken. The
     * ZGW schema caps anpIdentificatie at 17 characters, which the EVL prefix
     * plus a bigint id stays well within.
     */
    public static function anpIdentificatieForUser(?int $userId): ?string
    {
        return $userId === null ? null : sprintf('EVL%d', $userId);
    }

    /**
     * The organisation rol as our own OpenZaak has always received it.
     *
     * annIdentificatie is the standard-conformant carrier for the company
     * number on this betrokkeneType: RolNietNatuurlijkPersoon has no kvkNummer
     * property in any Zaken API release from 1.0 up to and including 1.7, while
     * annIdentificatie has been defined here since 1.0. kvkNummer is a
     * non-standard extra that our own instance read before this builder
     * existed, so it is sent alongside and nowhere else.
     *
     * @param  array<string, mixed>  $initiator
     * @return array<string, mixed>
     */
    private static function nietNatuurlijkPersoon(string $zaakUrl, string $roltype, array $initiator, ?string $kvkNummer, int $naamMax): array
    {
        return [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'niet_natuurlijk_persoon',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => self::contactpersoonRol($initiator, $naamMax),
            'betrokkeneIdentificatie' => array_filter([
                'statutaireNaam' => self::bounded($initiator['organisatie_naam'] ?? null, self::STATUTAIRE_NAAM_MAX),
                'annIdentificatie' => $kvkNummer,
                'kvkNummer' => $kvkNummer,
            ]),
        ];
    }

    /**
     * The organisation rol for every connection other than our own.
     *
     * A KvK number belongs on a vestiging in the ZGW standard: RolVestiging is
     * the only betrokkeneType that defines a kvkNummer property (added in Zaken
     * API 1.3.0 and present ever since), so this is the one place a receiving
     * instance can store the number as a company number instead of dropping it.
     * The rol stays on the same initiator roltype; vestiging is a
     * betrokkeneType, and RolType carries no betrokkeneType binding at all.
     *
     * Only what the form actually asks for is sent. vestigingsNummer is not
     * asked and is not invented, and the organisation address is not the
     * vestiging address, so no verblijfsadres either. RolVestiging requires no
     * field, so a rol on handelsnaam alone (hashed KvK on a rerun) is valid.
     *
     * @param  array<string, mixed>  $initiator
     * @return array<string, mixed>
     */
    private static function vestiging(string $zaakUrl, string $roltype, array $initiator, ?string $kvkNummer, int $naamMax): array
    {
        $handelsnaam = self::bounded($initiator['organisatie_naam'] ?? null, self::HANDELSNAAM_MAX);

        return [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'vestiging',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => self::contactpersoonRol($initiator, $naamMax),
            'betrokkeneIdentificatie' => array_filter([
                'kvkNummer' => $kvkNummer,
                // handelsnaam is a list in the schema, and we know one name.
                'handelsnaam' => $handelsnaam === null ? null : [$handelsnaam],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $initiator
     * @return array<string, mixed>
     */
    private static function natuurlijkPersoon(string $zaakUrl, string $roltype, FormState $state, array $initiator, ?string $anpIdentificatie, int $naamMax): array
    {
        $voornaam = (string) $state->get('watIsUwVoornaam');
        $achternaam = (string) $state->get('watIsUwAchternaam');
        $naam = trim($voornaam.' '.$achternaam);
        $adres = $initiator['natuurlijk_persoon_adres'] ?? null;

        $rolData = [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'natuurlijk_persoon',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => self::contactpersoonRol($initiator, $naamMax),
            'betrokkeneIdentificatie' => array_filter([
                'anpIdentificatie' => $anpIdentificatie,
                'geslachtsnaam' => self::bounded($achternaam, self::GESLACHTSNAAM_MAX),
                'voornamen' => self::bounded($voornaam, self::VOORNAMEN_MAX),
            ]),
        ];

        $afwijkendeNaam = self::bounded($naam, self::AFWIJKENDE_NAAM_MAX);
        if ($afwijkendeNaam !== null) {
            $rolData['afwijkendeNaamBetrokkene'] = $afwijkendeNaam;
        }

        $verblijfsadres = self::verblijfsadres($adres);
        if ($verblijfsadres !== null) {
            $rolData['betrokkeneIdentificatie']['verblijfsadres'] = $verblijfsadres;
        }

        return $rolData;
    }

    /**
     * A verblijfsadres is only sent for a Dutch address with a plain huisnummer
     * inside the schema's integer range: aoaHuisnummer is required on the
     * address, so an address that cannot supply a valid one is left out
     * altogether rather than risking a 400 that would abort the submit chain.
     *
     * The optional parts follow the same rule on a smaller scale: a huisletter or
     * huisnummertoevoeging that does not fit its bound is dropped, and so is a
     * postcode, because a cut postcode points at another place entirely. The
     * two required names are cut to their bound instead, since leaving them out
     * would make the address invalid on its own.
     *
     * @param  array<string, mixed>|mixed  $adres
     * @return array<string, mixed>|null
     */
    private static function verblijfsadres(mixed $adres): ?array
    {
        if (! is_array($adres) || ! Arr::has($adres, ['postcode', 'plaatsnaam', 'huisnummer'])) {
            return null;
        }
        if (! empty($adres['land']) && strtolower((string) $adres['land']) !== 'nederland') {
            return null;
        }

        $huisnummer = trim((string) $adres['huisnummer']);
        if (! ctype_digit($huisnummer) || (int) $huisnummer > self::HUISNUMMER_MAX) {
            return null;
        }

        $huisletter = trim((string) ($adres['huisletter'] ?? ''));
        $toevoeging = trim((string) ($adres['huisnummertoevoeging'] ?? ''));
        $straatnaam = trim((string) ($adres['straatnaam'] ?? ''));
        $postcode = str_replace(' ', '', (string) $adres['postcode']);

        return array_filter([
            'aoaIdentificatie' => config('app.name').'-persoonsadres-'.Str::uuid(),
            'wplWoonplaatsNaam' => Str::substr((string) $adres['plaatsnaam'], 0, self::WOONPLAATSNAAM_MAX),
            'gorOpenbareRuimteNaam' => Str::substr($straatnaam !== '' ? $straatnaam : 'adres', 0, self::OPENBARE_RUIMTE_NAAM_MAX),
            'aoaPostcode' => Str::length($postcode) <= self::POSTCODE_MAX ? $postcode : null,
            'aoaHuisnummer' => (int) $huisnummer,
            'aoaHuisletter' => strlen($huisletter) === self::HUISLETTER_MAX ? $huisletter : null,
            'aoaHuisnummertoevoeging' => ($toevoeging !== '' && strlen($toevoeging) <= self::HUISNUMMERTOEVOEGING_MAX) ? $toevoeging : null,
        ], fn ($v) => $v !== null);
    }
}
