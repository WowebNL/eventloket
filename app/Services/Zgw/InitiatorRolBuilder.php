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
     * OneGround's Zaken API v1.5 validator caps contactpersoonRol.naam at 40
     * characters: it kept the Zaken API 1.3/1.4 limit and never raised it. Our
     * client sends no Api-Version header, so a request to a OneGround backend
     * lands on that stricter v1.5 contract, and a longer composed name
     * (voornaam plus achternaam) is rejected with a 400 on the rol POST.
     */
    private const CONTACTPERSOON_NAAM_MAX_ONEGROUND = 40;

    /**
     * Every other backend follows the VNG 1.5 OAS, where ContactPersoonRol.naam
     * is maxLength 200 (OpenZaak's column matches). So a non-OneGround
     * connection, including our own OpenZaak, keeps the standard bound and is
     * not truncated at 40 for a limit that is not its own.
     */
    private const CONTACTPERSOON_NAAM_MAX_DEFAULT = 200;

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
     * The contactpersoonRol block for the rol, with naam bounded to the limit
     * that applies to this connection ($naamMax). Shared by every
     * betrokkeneType variant, since contactpersoonRol travels with all of them,
     * so an organisation with a KvK number and a long contact name is capped
     * just like a natuurlijk persoon. The full name survives elsewhere
     * (afwijkendeNaamBetrokkene, geslachtsnaam plus voornamen), so a plain cut
     * to the hard limit is enough. A name of $naamMax characters or shorter is
     * left byte-for-byte as is.
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

        if (isset($contactpersoon['naam']) && is_string($contactpersoon['naam'])) {
            $contactpersoon['naam'] = Str::substr($contactpersoon['naam'], 0, $naamMax);
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
     */
    private static function kvkNummer(mixed $kvk): ?string
    {
        if (! is_string($kvk) || str_starts_with($kvk, HashIdentifyingAttributes::HASH_PREFIX)) {
            return null;
        }

        return $kvk;
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
                'statutaireNaam' => $initiator['organisatie_naam'] ?? null,
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
        $organisatieNaam = $initiator['organisatie_naam'] ?? null;

        return [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'vestiging',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => self::contactpersoonRol($initiator, $naamMax),
            'betrokkeneIdentificatie' => array_filter([
                'kvkNummer' => $kvkNummer,
                // handelsnaam is a list in the schema, and we know one name.
                'handelsnaam' => is_string($organisatieNaam) && $organisatieNaam !== '' ? [$organisatieNaam] : null,
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
                'geslachtsnaam' => $achternaam !== '' ? $achternaam : null,
                'voornamen' => $voornaam !== '' ? $voornaam : null,
            ]),
        ];

        if ($naam !== '') {
            $rolData['afwijkendeNaamBetrokkene'] = $naam;
        }

        $verblijfsadres = self::verblijfsadres($adres);
        if ($verblijfsadres !== null) {
            $rolData['betrokkeneIdentificatie']['verblijfsadres'] = $verblijfsadres;
        }

        return $rolData;
    }

    /**
     * A verblijfsadres is only sent for a Dutch address with a plain numeric
     * huisnummer: the ZGW schema types aoaHuisnummer as an integer, caps
     * aoaHuisletter at one character and aoaHuisnummertoevoeging at four, so
     * values outside those bounds are dropped rather than risking a 400 that
     * would abort the submit chain.
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
        if (! ctype_digit($huisnummer)) {
            return null;
        }

        $huisletter = trim((string) ($adres['huisletter'] ?? ''));
        $toevoeging = trim((string) ($adres['huisnummertoevoeging'] ?? ''));
        $straatnaam = trim((string) ($adres['straatnaam'] ?? ''));

        return array_filter([
            'aoaIdentificatie' => config('app.name').'-persoonsadres-'.Str::uuid(),
            'wplWoonplaatsNaam' => $adres['plaatsnaam'],
            'gorOpenbareRuimteNaam' => $straatnaam !== '' ? $straatnaam : 'adres',
            'aoaPostcode' => str_replace(' ', '', (string) $adres['postcode']),
            'aoaHuisnummer' => (int) $huisnummer,
            'aoaHuisletter' => strlen($huisletter) === 1 ? $huisletter : null,
            'aoaHuisnummertoevoeging' => ($toevoeging !== '' && strlen($toevoeging) <= 4) ? $toevoeging : null,
        ], fn ($v) => $v !== null);
    }
}
