<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FormSessionRequest;
use App\Models\FormsubmissionSession;

class FormSessionController extends Controller
{
    public function __invoke(FormSessionRequest $request)
    {
        $data = $request->validated();
        $formSubmission = FormsubmissionSession::where('uuid', $data['submission_uuid'])->first();
        /** @var \App\Models\Organisation $organisation */
        $organisation = $formSubmission->organisation;

        /** @var \App\Models\User $user */
        $user = $formSubmission->user;
        $data = [
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
            'organisation_address' => '',
        ];
        if ($organisation->bag_address) {
            $data['organisation_address'] = [
                'city' => $organisation->bag_address->woonplaatsnaam,
                'postcode' => $organisation->bag_address->postcode,
                'streetName' => $organisation->bag_address->straatnaam,
                'houseNumber' => $organisation->bag_address->huisnummer,
                'houseNumberAddition' => $organisation->bag_address->huisnummertoevoeging,
                'houseLetter' => $organisation->bag_address->huisletter,
            ];
        }

        return response()->json(['message' => 'Valid session', 'data' => $data]);
    }
}
