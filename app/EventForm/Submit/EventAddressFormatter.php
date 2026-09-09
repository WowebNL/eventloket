<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\State\FormState;
use App\Support\Helpers\ArrayHelper;

/**
 * Formats the BAG addresses of an aanvraag into a readable label, following the
 * PDOK weergavenaam ("Coriovallumstraat 32A, 6411CD Heerlen").
 *
 * The addresses are read straight from the form snapshot, so no Locatieserver
 * call is needed and a retry always produces the same label. Both the current
 * AddressNL subfield keys (straatnaam, woonplaatsnaam, huisnummer) and the old
 * Open Forms keys (streetName, city, houseNumber) are accepted, because
 * submissions from the Open Forms era carry the latter.
 */
final class EventAddressFormatter
{
    /**
     * Every BAG address of the aanvraag, comma separated, or null when the
     * aanvraag has none (an outdoor event or a route).
     */
    public static function fromState(FormState $state): ?string
    {
        $gebouwen = $state->get('adresVanDeGebouwEn');
        if (! is_array($gebouwen)) {
            return null;
        }

        $labels = [];
        foreach ($gebouwen as $entry) {
            // The address lives one level deeper in the repeater row, under a
            // key that differs per form version, so it is located by content.
            $address = is_array($entry) ? ArrayHelper::findElementWithKey($entry, 'postcode') : null;
            if ($address === null) {
                continue;
            }

            $label = self::format($address);
            if ($label !== null) {
                $labels[] = $label;
            }
        }

        return $labels !== [] ? implode(', ', $labels) : null;
    }

    /**
     * A single address as "Straatnaam 12A-3, 6411CD Heerlen". Missing parts are
     * left out rather than producing stray spaces or commas: an address whose
     * PDOK lookup never succeeded can miss its street or city.
     *
     * @param  array<string, mixed>  $address
     */
    public static function format(array $address): ?string
    {
        $street = trim(implode(' ', array_filter([
            self::value($address, ['straatnaam', 'streetName']),
            self::houseNumber($address),
        ])));

        $postcode = self::value($address, ['postcode']);
        $place = trim(implode(' ', array_filter([
            $postcode === null ? null : strtoupper(str_replace(' ', '', $postcode)),
            self::value($address, ['woonplaatsnaam', 'city', 'plaatsnaam']),
        ])));

        $parts = array_filter([$street, $place], fn (string $part): bool => $part !== '');

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * Huisnummer with its huisletter attached and the toevoeging after a dash,
     * the way a Dutch address is written: "12A-3".
     *
     * @param  array<string, mixed>  $address
     */
    private static function houseNumber(array $address): ?string
    {
        $huisnummer = self::value($address, ['huisnummer', 'houseNumber']);
        if ($huisnummer === null) {
            return null;
        }

        $huisletter = self::value($address, ['huisletter', 'houseLetter']);
        $toevoeging = self::value($address, ['huisnummertoevoeging', 'houseNumberAddition']);

        return $huisnummer
            .($huisletter ?? '')
            .($toevoeging === null ? '' : '-'.$toevoeging);
    }

    /**
     * First non-empty value among the given keys, trimmed.
     *
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private static function value(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $address[$key] ?? null;
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
