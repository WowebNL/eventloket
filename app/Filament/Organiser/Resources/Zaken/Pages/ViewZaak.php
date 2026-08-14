<?php

namespace App\Filament\Organiser\Resources\Zaken\Pages;

use App\Filament\Organiser\Pages\EventFormDraftsPage;
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
            // "Repeat aanvraag" — start a new aanvraag prefilled with the
            // data of this zaak. Lands on the concepts overview, which puts
            // the prefill in a new concept (existing concepts are never
            // overwritten). PrefillLoader falls back silently to empty
            // values for fields that have since left the schema.
            // On a vooraankondiging the dedicated convert action below
            // replaces this one.
            Action::make('prefil_new_request')
                ->label('Nieuwe aanvraag met deze gegevens')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->tooltip('Start een nieuwe aanvraag waarbij de ingevulde gegevens uit deze zaak vooraf zijn ingevuld. U kunt alles aanpassen voordat u opnieuw indient.')
                ->visible(fn (Zaak $record): bool => $record->form_state_snapshot !== null && ! $record->isVooraankondiging())
                ->action(function (Zaak $record) {
                    $this->redirect(EventFormDraftsPage::getUrl([
                        'tenant' => Filament::getTenant(),
                        'prefill_from_zaak' => $record->id,
                    ]));
                }),
            // Convert a vooraankondiging into the definitive aanvraag
            // (issue #10). Same prefill path; EventFormDraftsPage detects
            // that the source is a vooraankondiging, flips the form to the
            // regular aanvraag flow and presets the link fields so the
            // zaaknummer is carried over into a locked field. Disappears
            // once a definitive aanvraag is linked — a vooraankondiging
            // is converted at most once.
            Action::make('convert_vooraankondiging')
                ->label('Definitieve aanvraag indienen')
                ->icon('heroicon-o-arrow-right-circle')
                ->tooltip('Start de definitieve aanvraag voor dit evenement. De gegevens uit uw vooraankondiging zijn vooraf ingevuld en het zaaknummer van de vooraankondiging wordt automatisch meegenomen.')
                ->visible(fn (Zaak $record): bool => $record->form_state_snapshot !== null && $record->isVooraankondiging() && $record->opgevolgdDoor()->doesntExist())
                ->action(function (Zaak $record) {
                    $this->redirect(EventFormDraftsPage::getUrl([
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
