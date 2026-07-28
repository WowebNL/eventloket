<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\EventForm\State\FormState;
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
 * - has a KvK number → niet_natuurlijk_persoon (statutaireNaam, kvkNummer)
 * - otherwise        → natuurlijk_persoon (voornamen, geslachtsnaam,
 *   anpIdentificatie, verblijfsadres)
 *
 * A natuurlijk_persoon rol needs an identifying attribute for a ZGW backend to
 * materialise a betrokkene (OneGround/RX Mission shows nothing for a rol whose
 * betrokkeneIdentificatie only carries name parts). We have no BSN, so a stable
 * application-issued anpIdentificatie derived from the organiser user id is
 * sent, mirroring kvkNummer on the organisation variant.
 */
final class InitiatorRolBuilder
{
    /**
     * @param  array<string, mixed>  $initiator  output of ZaakeigenschappenMap::buildInitiator()
     * @return array<string, mixed>|null rol payload, or null when there is no initiator data
     */
    public static function build(string $zaakUrl, string $roltype, FormState $state, array $initiator, ?string $anpIdentificatie = null): ?array
    {
        if ($initiator === []) {
            return null;
        }

        return isset($initiator['kvk']) && $initiator['kvk']
            ? self::nietNatuurlijkPersoon($zaakUrl, $roltype, $initiator)
            : self::natuurlijkPersoon($zaakUrl, $roltype, $state, $initiator, $anpIdentificatie);
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
    private static function nietNatuurlijkPersoon(string $zaakUrl, string $roltype, array $initiator): array
    {
        return [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'niet_natuurlijk_persoon',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => $initiator['contactpersoon'] ?? null,
            // We send only kvkNummer as the company identifier, for every
            // connection (OpenZaak included). annIdentificatie is deliberately
            // omitted: not every ZGW instance accepts it and the KvK number is
            // the canonical identifier.
            'betrokkeneIdentificatie' => array_filter([
                'statutaireNaam' => $initiator['organisatie_naam'] ?? null,
                'kvkNummer' => $initiator['kvk'],
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
