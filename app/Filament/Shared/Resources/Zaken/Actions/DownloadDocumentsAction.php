<?php

namespace App\Filament\Shared\Resources\Zaken\Actions;

use App\Jobs\Zaak\CreateDocumentsZipJob;
use App\Models\Zaak;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class DownloadDocumentsAction
{
    /**
     * Number of documents above which the zip is created asynchronously.
     */
    private const ASYNC_THRESHOLD = 3;

    public static function make(Zaak $zaak): BulkAction
    {
        return BulkAction::make('download-documents')
            ->label(__('Download selectie'))
            ->icon('heroicon-o-arrow-down-tray')
            ->requiresConfirmation()
            ->modalHeading(__('Documenten downloaden'))
            ->modalDescription(fn (Collection $records): string => trans_choice(
                '{1} 1 document downloaden als ZIP-bestand?|[2,*] :count documenten downloaden als ZIP-bestand?',
                $records->count(),
                ['count' => $records->count()],
            ))
            ->modalSubmitActionLabel(__('Downloaden'))
            ->action(function (Collection $records, BulkAction $action) use ($zaak): void {
                $uuids = $records->pluck('uuid')->filter()->values()->all();

                if (empty($uuids)) {
                    return;
                }

                if (count($uuids) <= self::ASYNC_THRESHOLD) {
                    $token = CreateDocumentsZipJob::buildZip($zaak, $uuids, (int) auth()->id());

                    if ($token !== null) {
                        $action->getLivewire()->js(
                            "window.open('".route('zaak.documents.zip', ['zaak' => $zaak->id, 'token' => $token])."', '_blank')"
                        );

                        activity('document')
                            ->event('multi_download')
                            ->causedBy(auth()->user())
                            ->performedOn($zaak)
                            ->withProperties(['count' => count($uuids)])
                            ->log(__('activity/event.multi_download', ['count' => count($uuids)]));

                        return;
                    }
                }

                CreateDocumentsZipJob::dispatch($zaak, $uuids, (int) auth()->id());

                Notification::make()
                    ->title(__('Download wordt voorbereid'))
                    ->body(__('Je ontvangt een melding zodra de download klaar is.'))
                    ->info()
                    ->send();
            });
    }
}
