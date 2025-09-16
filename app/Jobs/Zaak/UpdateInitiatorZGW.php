<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\ObjectsApi\FormSubmissionObject;
use App\ValueObjects\OzZaak;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

class UpdateInitiatorZGW implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private string $zaakUrlZGW) {}

    /**
     * Execute the job.
     */
    public function handle(Openzaak $openzaak, ObjectsApi $objectsapi): void
    {
        $zaak = new OzZaak(...$openzaak->get($this->zaakUrlZGW.'?expand=rollen,zaakobjecten')->toArray());
        $formSubmissionObject = new FormSubmissionObject(...$objectsapi->get(basename($zaak->data_object_url))->toArray());

        if ($formSubmissionObject->initiator && $zaak->initiator) {

            if ($zaak->initiator->betrokkeneType === 'natuurlijk_persoon' && isset($formSubmissionObject->initiator['kvk']) && $formSubmissionObject->initiator['kvk']) {
                // TODO cleenup this mess comming from OF
                $adres = $formSubmissionObject->initiator['organisatie_adres'] ? json_decode(str_replace("'", '"', $formSubmissionObject->initiator['organisatie_adres']), true) : null;
                $rolData = [
                    'zaak' => $zaak->url,
                    'betrokkeneType' => 'niet_natuurlijk_persoon',
                    'roltype' => $zaak->initiator->roltype,
                    'roltoelichting' => 'inzender formulier',
                    'contactpersoonRol' => $formSubmissionObject->initiator['contactpersoon'] ?? null,
                    'betrokkeneIdentificatie' => [
                        'statutaireNaam' => $formSubmissionObject->initiator['organisatie_naam'],
                        'annIdentificatie' => $formSubmissionObject->initiator['kvk'],
                        'kvkNummer' => $formSubmissionObject->initiator['kvk'],
                    ],
                ];

                // if ($adres) { gives an error in Open Zaak API
                //     $rolData['betrokkeneIdentificatie']['verblijfsadres'] = [
                //         'aoaIdentificatie' => config('app.name').'-organisatieadres-'.Str::uuid(),
                //         'wplWoonplaatsNaam' => $adres['city'],
                //         'gorOpenbareRuimteNaam' => 'adres',
                //         'aoaPostcode' => $adres['postcode'],
                //         'aoaHuisnummer' => $adres['houseNumber'],
                //         'aoaHuisletter' => $adres['houseLetter'],
                //         'aoaHuisnummertoevoeging' => $adres['houseNumberAddition'],
                //     ];
                // }

                $openzaak->zaken()->rollen()->put($zaak->initiator->uuid, $rolData);

            } elseif (! isset($formSubmissionObject->initiator['kvk']) || ! $formSubmissionObject->initiator['kvk']) {
                // no kvk so initiator is natuurlijk_persoon

                $adres = $formSubmissionObject->initiator['natuurlijk_persoon_adres'];

                $rolData = [
                    'zaak' => $zaak->url,
                    'betrokkeneType' => 'natuurlijk_persoon',
                    'roltype' => $zaak->initiator->roltype,
                    'roltoelichting' => 'inzender formulier',
                    'contactpersoonRol' => $formSubmissionObject->initiator['contactpersoon'] ?? null,
                    'betrokkeneIdentificatie' => [
                        'geslachtsnaam' => $zaak->initiator->betrokkeneIdentificatie['geslachtsnaam'] ?? '',
                        'voornamen' => $zaak->initiator->betrokkeneIdentificatie['voornamen'] ?? '',
                    ],
                ];

                if (Arr::has($adres, ['land', 'postcode', 'plaatsnaam', 'straatnaam', 'huisnummer', 'huisletter', 'huisnummertoevoeging']) && (empty($adres['land']) || strtolower($adres['land']) == 'nederland')) {
                    $rolData['betrokkeneIdentificatie']['verblijfsadres'] = [
                        'aoaIdentificatie' => config('app.name').'-persoonsadres-'.Str::uuid(),
                        'wplWoonplaatsNaam' => $adres['plaatsnaam'],
                        'gorOpenbareRuimteNaam' => 'adres',
                        'aoaPostcode' => $adres['postcode'],
                        'aoaHuisnummer' => $adres['huisnummer'],
                        'aoaHuisletter' => $adres['huisletter'],
                        'aoaHuisnummertoevoeging' => $adres['huisnummertoevoeging'],
                    ];
                }

                $openzaak->zaken()->rollen()->put($zaak->initiator->uuid, $rolData);
            }

        }
    }
}
