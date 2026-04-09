<?php

namespace App\Http\Resources\Api;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventloketTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return ['valid' => false];
        }

        /** @var User $user */
        $user = $this->resource['user'];
        /** @var Organisation $organisation */
        $organisation = $this->resource['organisation'];

        $address = '';
        if ($organisation->bag_address) {
            $address = [
                'city' => $organisation->bag_address->woonplaatsnaam,
                'postcode' => $organisation->bag_address->postcode,
                'streetName' => $organisation->bag_address->straatnaam,
                'houseNumber' => $organisation->bag_address->huisnummer,
                'houseNumberAddition' => $organisation->bag_address->huisnummertoevoeging,
                'houseLetter' => $organisation->bag_address->huisletter,
            ];
        } elseif ($organisation->postbus_address) {
            $address = [
                'city' => $organisation->postbus_address->woonplaatsnaam,
                'postcode' => $organisation->postbus_address->postcode,
                'streetName' => 'Postbus',
                'houseNumber' => $organisation->postbus_address->postbusnummer,
                'houseNumberAddition' => '',
                'houseLetter' => '',
            ];
        }

        return [
            'valid' => true,
            'identifier' => $user->uuid,
            'data' => [
                'user_uuid' => $user->uuid,
                'organiser_uuid' => $organisation->uuid,
                'kvk' => $organisation->coc_number ?? '',
                'organisation_name' => $organisation->name,
                'organisation_email' => $organisation->email ?? '',
                'organisation_phone' => $organisation->phone ?? '',
                'user_email' => $user->email,
                'user_phone' => $user->phone ?? '',
                'user_first_name' => $user->first_name ?? '',
                'user_last_name' => $user->last_name ?? '',
                'organisation_address' => $address,
            ],
        ];
    }
}
