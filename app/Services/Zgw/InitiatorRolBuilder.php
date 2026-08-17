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
 * Two variants, matching the aanvrager:
 * - has a KvK number → niet_natuurlijk_persoon (statutaireNaam,
 *   annIdentificatie, and kvkNummer only towards the default connection)
 * - otherwise        → natuurlijk_persoon (voornamen, geslachtsnaam,
 *   anpIdentificatie, verblijfsadres)
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
     * @param  string  $connectionName  the connection the rol is posted to, which decides
     *                                  whether the non-standard kvkNummer is sent along
     * @param  array<string, mixed>  $initiator  output of ZaakeigenschappenMap::buildInitiator()
     * @return array<string, mixed>|null rol payload, or null when there is no initiator data
     */
    public static function build(string $connectionName, string $zaakUrl, string $roltype, FormState $state, array $initiator, ?string $anpIdentificatie = null): ?array
    {
        if ($initiator === []) {
            return null;
        }

        return isset($initiator['kvk']) && $initiator['kvk']
            ? self::nietNatuurlijkPersoon($connectionName, $zaakUrl, $roltype, $initiator, self::kvkNummer($initiator['kvk']))
            : self::natuurlijkPersoon($zaakUrl, $roltype, $state, $initiator, $anpIdentificatie);
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
     * registered on the statutaireNaam alone, so this guard covers both
     * annIdentificatie and kvkNummer.
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
     * @param  array<string, mixed>  $initiator
     * @return array<string, mixed>
     */
    private static function nietNatuurlijkPersoon(string $connectionName, string $zaakUrl, string $roltype, array $initiator, ?string $kvkNummer): array
    {
        return [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'niet_natuurlijk_persoon',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => $initiator['contactpersoon'] ?? null,
            // annIdentificatie is the standard-conformant carrier for the
            // company number. RolNietNatuurlijkPersoon has no kvkNummer
            // property in any Zaken API release from 1.0 up to and including
            // 1.7 (kvkNummer only exists on RolVestiging, added in 1.3.0),
            // while annIdentificatie has been defined here since 1.0. Sending
            // only kvkNummer therefore leaves the rol with a statutaireNaam and
            // no identifying attribute at all, which is what happened on a real
            // instance on 28-07-2026: the create response came back without an
            // error and without the property, innNnpId and annIdentificatie
            // both empty. innNnpId is not used for the number either, because
            // that field holds the chamber-issued RSIN and a conformant backend
            // validates it as such, which an eight-digit KvK number fails.
            //
            // kvkNummer is a non-standard extra and only goes to our own
            // default connection (OpenZaak), which read it before this builder
            // existed. Every other connection belongs to a municipality running
            // its own instance, and those get the standard payload only.
            'betrokkeneIdentificatie' => array_filter([
                'statutaireNaam' => $initiator['organisatie_naam'] ?? null,
                'annIdentificatie' => $kvkNummer,
                'kvkNummer' => ZgwConnectionConfig::isDefaultConnection($connectionName) ? $kvkNummer : null,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $initiator
     * @return array<string, mixed>
     */
    private static function natuurlijkPersoon(string $zaakUrl, string $roltype, FormState $state, array $initiator, ?string $anpIdentificatie): array
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
            'contactpersoonRol' => $initiator['contactpersoon'] ?? null,
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
