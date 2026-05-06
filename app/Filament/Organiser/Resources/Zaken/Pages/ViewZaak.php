<?php

namespace App\Filament\Organiser\Resources\Zaken\Pages;

use App\Filament\Organiser\Resources\Zaken\ZaakResource;
use App\Jobs\Zaak\AddFinalStatusZGW;
use App\Jobs\Zaak\AddResultaatZGW;
use App\Models\Users\MunicipalityUser;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Notifications\Result;
use App\ValueObjects\FinishZaakObject;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Bus;

class ViewZaak extends ViewRecord
{
    protected static string $resource = ZaakResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // "Herhaal aanvraag" — start een nieuwe aanvraag voorgevuld met de
            // gegevens van deze zaak. PrefillLoader valt stil terug op lege
            // waarden voor velden die inmiddels uit het schema zijn.
            Action::make('prefil_new_request')
                ->label('Nieuwe aanvraag met deze gegevens')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->tooltip('Start een nieuwe aanvraag waarbij de ingevulde gegevens uit deze zaak vooraf zijn ingevuld. U kunt alles aanpassen voordat u opnieuw indient.')
                ->action(function (Zaak $record) {
                    $this->redirect(route('filament.organiser.pages.aanvraag', [
                        'tenant' => Filament::getTenant(),
                        'prefill_from_zaak' => $record->id,
                    ]));
                }),
            Action::make('withdraw')
                ->tooltip(__('Wanneer u een aanvraag intrekt, wordt deze niet verder in behandeling genomen. De behandelaar ontvangt hiervan een melding. Het is niet mogelijk om het intrekken ongedaan te maken.'))
                ->label('Aanvraag intrekken')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Zaak $record): bool => ($record->openzaak && ! $record->openzaak->resultaat) && $record->zaaktype->intrekkenResultaatType !== null)
                ->action(function (Zaak $record) {
                    /** @var OrganiserUser $user */
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
                                /** @var MunicipalityUser $recipient */
                                $recipient->notify(new Result(
                                    zaak: $record,
                                    tenant: $record->organisation,
                                    title: $finishZaakObject->message_title,
                                    message: $finishZaakObject->message_content,
                                ));
                            }

                            if ($record->handled_status_set_by_user_id) {
                                /** @var MunicipalityUser $recipient */
                                $recipient = $record->handledStatusSetByUser;
                                $recipient->notify(new Result(
                                    zaak: $record,
                                    tenant: $record->municipality,
                                    title: $finishZaakObject->message_title,
                                    message: $finishZaakObject->message_content,
                                ));
                            } else {
                                foreach ($record->municipality->allReviewerUsers as $recipient) {
                                    /** @var MunicipalityUser $recipient */
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
