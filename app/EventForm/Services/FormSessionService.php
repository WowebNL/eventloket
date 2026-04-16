<?php

declare(strict_types=1);

namespace App\EventForm\Services;

use App\Models\Organisation;
use App\Models\User;

/**
 * Bouwt de `eventloketSession`-variable uit de ingelogde user + organisatie.
 *
 * In OF werd dit via HTTP-call naar /api/formsessions?submission_uuid=…
 * gedaan; in de Filament-context hebben we user + organisation direct
 * beschikbaar en slaan we de stap over.
 */
class FormSessionService
{
    /**
     * @return array<string, mixed>
     */
    public function buildFor(User $user, Organisation $organisation): array
    {
        return [
            'user_uuid' => $user->uuid,
            'organiser_uuid' => $organisation->uuid,
            'kvk' => $organisation->coc_number ?? '',
            'organisation_name' => $organisation->name,
            'organisation_email' => $organisation->email ?? '',
            'organisation_phone' => $organisation->phone ?? '',
            'user_email' => $user->email,
            'user_phone' => $user->phone,
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'organisation_address' => $this->formatAddress($organisation),
        ];
    }

    /**
     * @return array<string, string>|string Empty string when no address on file
     *                                      (behoud van OF response-shape).
     */
    private function formatAddress(Organisation $organisation): array|string
    {
        if ($organisation->bag_address) {
            return [
                'city' => $organisation->bag_address->woonplaatsnaam,
                'postcode' => $organisation->bag_address->postcode,
                'streetName' => $organisation->bag_address->straatnaam,
                'houseNumber' => (string) $organisation->bag_address->huisnummer,
                'houseNumberAddition' => $organisation->bag_address->huisnummertoevoeging ?? '',
                'houseLetter' => $organisation->bag_address->huisletter ?? '',
            ];
        }

        if ($organisation->postbus_address) {
            return [
                'city' => $organisation->postbus_address->woonplaatsnaam,
                'postcode' => $organisation->postbus_address->postcode,
                'streetName' => 'Postbus',
                'houseNumber' => (string) $organisation->postbus_address->postbusnummer,
                'houseNumberAddition' => '',
                'houseLetter' => '',
            ];
        }

        return '';
    }
}
