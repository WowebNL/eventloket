<?php

namespace App\Filament\Organiser\Resources\Zaken\Pages;

use App\Filament\Organiser\Resources\Zaken\ZaakResource;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Arr;
use Woweb\Openzaak\ObjectsApi;

class ViewZaak extends ViewRecord
{
    protected static string $resource = ZaakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prefil_new_request')
                ->label('Nieuwe aanvraag')
                ->tooltip('Start een nieuwe aanvraag waarbij de gegevens uit het aanvraagformulier van deze zaak vooraf ingevuld zijn.')
                ->action(function (Zaak $record) {
                    $failed = false;
                    if(isset($record->zaakdata->record['data']['data']) && $data = $record->zaakdata->record['data']['data']){
                        $withOutSections = [];
                        foreach($data as $item) {
                            $withOutSections = is_array($item) ? array_merge($withOutSections, $item) : array_merge($withOutSections, [$item]);
                        }

                        $withOutSections['user_uuid'] = auth()->user()->uuid;
                        $withOutSections['organiser_uuid'] = $record->organisation->uuid;

                        if($objectTypeUrl = config('services.open_forms.prefill_object_type_url')) {
                            $record = array_filter($withOutSections);

                            $resp = (new ObjectsApi)->create([
                                'type' => $objectTypeUrl,
                                'record' => [
                                    'typeVersion' => config('services.open_forms.prefill_object_type_version'),
                                    'data' => $record,
                                    'startAt' => now()->format('Y-m-d'),
                                ],
                            ])->toArray();

                            if (Arr::has($resp, 'uuid')) {
                                $this->redirect(route('filament.organiser.pages.new-request.{openform?}', ['tenant' => Filament::getTenant(), 'initial_data_reference' => Arr::get($resp, 'uuid')]));
                                Notification::make()
                                    ->success()
                                    ->title('Het aanvraag formulier is vooraf ingevuld')
                                    ->body('De gegevens uit het aanvraagformulier van de zaak zijn vooraf ingevuld in het formulier.')
                                    ->send();
                            } else {
                                $failed = true;
                            }
                        } else {
                            $failed = true;
                        } 
                    } else {
                        $failed = true;
                    }

                    if($failed) {
                        Notification::make()
                            ->warning()
                            ->title('Er is iets misgegaan bij het aanmaken van een nieuwe aanvraag.')
                            ->body('Het vooraf invullen van het formulier is mislukt. Probeer het nogmaals of start een nieuwe aanvraag zonder vooraf ingevulde gegevens.')
                            ->send();
                    }
                })
        ];
    }
}
