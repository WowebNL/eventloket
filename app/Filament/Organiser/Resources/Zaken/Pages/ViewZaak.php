<?php

namespace App\Filament\Organiser\Resources\Zaken\Pages;

use App\Filament\Organiser\Resources\Zaken\ZaakResource;
use App\Jobs\Zaak\AddFinalStatusZGW;
use App\Jobs\Zaak\AddResultaatZGW;
use App\Models\Zaak;
use App\Notifications\Result;
use App\ValueObjects\FinishZaakObject;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Woweb\Openzaak\ObjectsApi;

class ViewZaak extends ViewRecord
{
    protected static string $resource = ZaakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prefil_new_request')
                ->hidden()
                ->label('Nieuwe aanvraag')
                ->tooltip('Start een nieuwe aanvraag waarbij de gegevens uit het aanvraagformulier van deze zaak vooraf ingevuld zijn.')
                ->action(function (Zaak $record) {
                    $failed = false;
                    if (isset($record->zaakdata->record['data']['data']) && $data = $record->zaakdata->record['data']['data']) {
                        $withOutSections = [];
                        foreach ($data as $item) {
                            $withOutSections = is_array($item) ? array_merge($withOutSections, $item) : array_merge($withOutSections, [$item]);
                        }

                        $withOutSections['user_uuid'] = auth()->user()->uuid;
                        $withOutSections['organiser_uuid'] = $record->organisation->uuid;

                        if ($objectTypeUrl = config('services.open_forms.prefill_object_type_url')) {
                            $record = array_filter($withOutSections);

                            $resp = (new ObjectsApi)->create([
                                'type' => $objectTypeUrl,
                                'record' => [
                                    'typeVersion' => config('services.open_forms.prefill_object_type_version'),
                                    'data' => $record,
                                    'startAt' => now()->format('Y-m-d'),
                                ],
                            ])->toArray();

                            // dd($resp);

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

                    if ($failed) {
                        Notification::make()
                            ->warning()
                            ->title('Er is iets misgegaan bij het aanmaken van een nieuwe aanvraag.')
                            ->body('Het vooraf invullen van het formulier is mislukt. Probeer het nogmaals of start een nieuwe aanvraag zonder vooraf ingevulde gegevens.')
                            ->send();
                    }
                }),
            Action::make('withdraw')
                ->tooltip(__('Wanneer u een aanvraag intrekt, wordt deze niet verder in behandeling genomen. De behandelaar ontvangt hiervan een melding. Het is niet mogelijk om het intrekken ongedaan te maken.'))
                ->label('Aanvraag intrekken')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Zaak $record): bool => ($record->openzaak && ! $record->openzaak->resultaat) && $record->zaaktype->intrekkenResultaatType !== null)
                ->action(function (Zaak $record) {
                    /** @var \App\Models\Users\OrganiserUser $user */
                    $user = auth()->user();
                    $finishZaakObject = new FinishZaakObject(
                        zaak: $record,
                        user: $user,
                        resultaattype: $record->zaaktype->intrekkenResultaatType['url'],
                        besluittype: null,
                        datum_besluit: null,
                        ingangsdatum: now()->format('Y-m-d'),
                        vervaldatum: null,
                        result_toelichting: __('Ingetrokken door organisator via de applicatie.'),
                        message_title: __('Aanvraag :id ingetrokken', ['id' => $record->public_id]),
                        message_content: __('De aanvraag met referentie :id is ingetrokken door de organisator.', ['id' => $record->public_id]),
                    );

                    Bus::chain([
                        new AddResultaatZGW($finishZaakObject),
                        new AddFinalStatusZGW($finishZaakObject),
                        function () use ($record, $finishZaakObject) {
                            foreach ($record->organisation->users as $recipient) {
                                /** @var \App\Models\Users\MunicipalityUser $recipient */
                                $recipient->notify(new Result(
                                    zaak: $record,
                                    tenant: $record->organisation,
                                    title: $finishZaakObject->message_title,
                                    message: $finishZaakObject->message_content,
                                ));
                            }

                            if ($record->handled_status_set_by_user_id) {
                                /** @var \App\Models\Users\MunicipalityUser $recipient */
                                $recipient = $record->handledStatusSetByUser;
                                $recipient->notify(new Result(
                                    zaak: $record,
                                    tenant: $record->municipality,
                                    title: $finishZaakObject->message_title,
                                    message: $finishZaakObject->message_content,
                                ));
                            } else {
                                foreach ($record->municipality->allReviewerUsers as $recipient) {
                                    /** @var \App\Models\Users\MunicipalityUser $recipient */
                                    $recipient->notify(new Result(
                                        zaak: $record,
                                        tenant: $record->municipality,
                                        title: $finishZaakObject->message_title,
                                        message: $finishZaakObject->message_content,
                                    ));
                                }
                            }
                        },
                    ])->dispatch();

                    /** @disregard */
                    $record->reference_data = new ZaakReferenceData(...array_merge($record->reference_data->toArray(), ['resultaat' => __('wordt momementeel verwerkt...')])); // @phpstan-ignore assign.propertyReadOnly

                    $record->save();
                    $record->clearZgwCache();

                    Notification::make()
                        ->success()
                        ->title('De aanvraag is ingetrokken')
                        ->body('De behandelaar is op de hoogte gebracht van het intrekken van deze aanvraag.')
                        ->send();

                }),
        ];
    }
}
