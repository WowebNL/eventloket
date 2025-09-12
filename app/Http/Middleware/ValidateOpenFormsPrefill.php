<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use Woweb\Openzaak\ObjectsApi;

/**
 * @deprecated for now, as it causes issues with openform submission, workaround for now is to save the of submission id from localstorage to the db
 * keep code for later use
 */
class ValidateOpenFormsPrefill
{
    public function __construct(protected ObjectsApi $objectsApi) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($objectId = $request->query('initial_data_reference')) {
            $object = $this->objectsApi->get($objectId)->toArray();
            /** @var \App\Models\Organisation $tenant */
            $tenant = Filament::getTenant();
            if (Arr::has($object, ['record.data', 'record.data.user_uuid', 'record.data.organiser_uuid'])) {
                if (Arr::get($object, 'record.data.user_uuid') == auth()->user()->uuid
                    && Arr::get($object, 'record.data.organiser_uuid') == $tenant->uuid) {
                    return $next($request);
                }
            }

            return redirect()->to($request->fullUrlWithoutQuery('initial_data_reference'));

        } elseif ($objectTypeUrl = config('services.open_forms.prefill_object_type_url')) {
            /** @var \App\Models\Organisation $organisation */
            $organisation = Filament::getTenant();

            $record = ['user_uuid' => auth()->user()->uuid,
                'organiser_uuid' => $organisation->uuid,
                'kvk' => $organisation->coc_number,
                'organisation_name' => $organisation->name,
                'organisation_email' => $organisation->email,
                'organisation_phone' => $organisation->phone,
                'user_email' => auth()->user()->email,
                'user_phone' => auth()->user()->phone,
                'user_first_name' => auth()->user()->first_name,
                'user_last_name' => auth()->user()->last_name,
            ];

            if ($organisation->bag_address) {
                $record['organisation_address'] = [
                    'city' => $organisation->bag_address->woonplaatsnaam,
                    'postcode' => $organisation->bag_address->postcode,
                    'streetName' => $organisation->bag_address->straatnaam,
                    'houseNumber' => $organisation->bag_address->huisnummer,
                    'houseNumberAddition' => $organisation->bag_address->huisnummertoevoeging,
                    'houseLetter' => $organisation->bag_address->huisletter,
                ];
            }

            $record = array_filter($record);

            $resp = $this->objectsApi->create([
                'type' => $objectTypeUrl,
                'record' => [
                    'typeVersion' => config('services.open_forms.prefill_object_type_version'),
                    'data' => $record,
                    'startAt' => now()->format('Y-m-d'),
                ],
            ])->toArray();

            if (Arr::has($resp, 'uuid')) {
                return redirect()->to($request->fullUrlWithQuery(['initial_data_reference' => Arr::get($resp, 'uuid')]));
            }

        }

        return $next($request);
    }
}
