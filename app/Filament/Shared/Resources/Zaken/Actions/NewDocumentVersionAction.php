<?php

namespace App\Filament\Shared\Resources\Zaken\Actions;

use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Woweb\Openzaak\Openzaak;

class NewDocumentVersionAction
{
    public static function make(Zaak $zaak): Action
    {
        return Action::make('new-version')
            ->label(__('Nieuwe versie toevoegen'))
            ->icon('heroicon-o-plus-circle')
            ->modalSubmitAction(fn (Action $action) => $action->label(__('Nieuwe versie toevoegen')))
            ->schema(fn (array $record) => self::schema($record['titel']))
            ->modalAutofocus(false)
            ->action(function (array $record, array $data, Action $action) use ($zaak): void {

                self::createNewDocumentVersion($record['uuid'], $data, $zaak);

                Notification::make()
                    ->title('Nieuwe versie is toegevoegd')
                    ->success()
                    ->send();

                $action->getLivewire()->dispatch('refreshTable');
            });
    }

    public static function schema(?string $documentTitle = null): array
    {
        return [
            TextInput::make('titel')
                ->label(__('Titel'))
                ->required()
                ->maxLength(255)
                ->default($documentTitle),
            FileUpload::make('file')
                ->label(__('Bestand'))
                ->required()
                ->maxSize(20480) // 20MB
                ->directory('documents')
                ->visibility('private')
                ->storeFileNamesIn('file_name'),
        ];
    }

    public static function createNewDocumentVersion(string $documentUuid, array $data, Zaak $zaak)
    {
        $oz = new Openzaak;
        $lockString = $oz->documenten()->enkelvoudiginformatieobjecten()->lock($documentUuid);
        $oz->documenten()->enkelvoudiginformatieobjecten()->patch($documentUuid,
            [
                'inhoud' => base64_encode(Storage::get($data['file'])),
                'titel' => $data['titel'],
                'auteur' => auth()->user()->name,
                'bestandsnaam' => $data['file_name'],
                'bestandsomvang' => Storage::size($data['file']),
                'formaat' => Storage::mimeType($data['file']),
                'lock' => $lockString,
            ]
        );
        $oz->documenten()->enkelvoudiginformatieobjecten()->unlock($documentUuid, $lockString);

        Storage::delete($data['file']);

        Cache::forget("zaak.{$zaak->id}.documenten");
    }
}
