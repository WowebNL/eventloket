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
 * - otherwise        → natuurlijk_persoon (voornamen, geslachtsnaam, adres)
 */
final class InitiatorRolBuilder
{
    /**
     * @param  array<string, mixed>  $initiator  output of ZaakeigenschappenMap::buildInitiator()
     * @return array<string, mixed>|null rol payload, or null when there is no initiator data
     */
    public static function build(string $zaakUrl, string $roltype, FormState $state, array $initiator): ?array
    {
        if ($initiator === []) {
            return null;
        }

        return isset($initiator['kvk']) && $initiator['kvk']
            ? self::nietNatuurlijkPersoon($zaakUrl, $roltype, $initiator)
            : self::natuurlijkPersoon($zaakUrl, $roltype, $state, $initiator);
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
    private static function natuurlijkPersoon(string $zaakUrl, string $roltype, FormState $state, array $initiator): array
    {
        $voornaam = (string) $state->get('watIsUwVoornaam');
        $achternaam = (string) $state->get('watIsUwAchternaam');
        $adres = $state->get('natuurlijkPersoonAdres');

        $rolData = [
            'zaak' => $zaakUrl,
            'betrokkeneType' => 'natuurlijk_persoon',
            'roltype' => $roltype,
            'roltoelichting' => 'inzender formulier',
            'contactpersoonRol' => $initiator['contactpersoon'] ?? null,
            'betrokkeneIdentificatie' => array_filter([
                'geslachtsnaam' => $achternaam !== '' ? $achternaam : null,
                'voornamen' => $voornaam !== '' ? $voornaam : null,
            ]),
        ];

        if (is_array($adres) && Arr::has($adres, ['postcode', 'plaatsnaam', 'huisnummer'])
            && (empty($adres['land']) || strtolower((string) $adres['land']) === 'nederland')) {
            $rolData['betrokkeneIdentificatie']['verblijfsadres'] = [
                'aoaIdentificatie' => config('app.name').'-persoonsadres-'.Str::uuid(),
                'wplWoonplaatsNaam' => $adres['plaatsnaam'] ?? null,
                'gorOpenbareRuimteNaam' => 'adres',
                'aoaPostcode' => $adres['postcode'] ?? null,
                'aoaHuisnummer' => $adres['huisnummer'] ?? null,
                'aoaHuisletter' => $adres['huisletter'] ?? null,
                'aoaHuisnummertoevoeging' => $adres['huisnummertoevoeging'] ?? null,
            ];
        }

        return $rolData;
    }
}
