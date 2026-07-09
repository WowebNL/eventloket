<?php

namespace App\Filament\Shared\Resources\Zaken\Actions;

use App\Models\Zaak;
use App\Support\Documents\DocumentVersionAuthorizer;
use App\Support\Uploads\DocumentUploadType;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Woweb\Zgw\Facades\Zgw;

class NewDocumentVersionAction
{
    public static function make(Zaak $zaak): Action
    {
        return Action::make('new-version')
            ->label(__('Nieuwe versie'))
            ->icon('heroicon-o-plus-circle')
            ->modalSubmitAction(fn (Action $action) => $action->label(__('Nieuwe versie toevoegen')))
            ->schema(fn (array $record) => self::schema($record['titel']))
            ->modalAutofocus(false)
            ->authorize(fn (): bool => auth()->user()->can('uploadDocument', $zaak))
            ->visible(fn (array $record): bool => DocumentVersionAuthorizer::canAddVersion(auth()->user(), $zaak, $record['uuid']))
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
                ->maxSize(61440) // 60MB
                ->mimeTypeMap(config('app.document_mime_type_mappings'))
                ->rule(DocumentUploadType::fileUploadRule())
                ->directory('documents')
                ->visibility('private')
                ->storeFileNamesIn('file_name'),
        ];
    }

    public static function createNewDocumentVersion(string $documentUuid, array $data, Zaak $zaak)
    {
        $connection = Zgw::connection($zaak->zgwConnectionName());
        $formaat = DocumentUploadType::determineFormaat($data['file'], $data['file_name'] ?? null);
        $bestandsnaam = DocumentUploadType::ensureFileNameHasExtension($data['file_name'] ?? '', $formaat);
        $lockString = $connection->documenten()->enkelvoudiginformatieobjecten()->lock($documentUuid);

        // Always release the lock, even when the patch fails. Without this a
        // failed patch left the document locked, and every subsequent "Nieuwe
        // versie" attempt then failed on the lock with a 400 "document is al
        // gelocked" — surfacing as an error for every user on that document.
        try {
            $connection->documenten()->enkelvoudiginformatieobjecten()->patch($documentUuid,
                [
                    'inhoud' => base64_encode(Storage::get($data['file'])),
                    'titel' => $data['titel'],
                    'auteur' => auth()->user()->name,
                    'bestandsnaam' => $bestandsnaam,
                    'bestandsomvang' => Storage::size($data['file']),
                    'formaat' => $formaat,
                    'lock' => $lockString,
                    'indicatieGebruiksrecht' => false,
                ]
            );
        } finally {
            try {
                $connection->documenten()->enkelvoudiginformatieobjecten()->unlock($documentUuid, $lockString);
            } catch (Throwable $unlockError) {
                // Never let a failing unlock mask the original patch error.
                Log::warning('Releasing the document lock after a new-version patch did not succeed', [
                    'zaak_id' => $zaak->id,
                    'document_uuid' => $documentUuid,
                    'error' => $unlockError->getMessage(),
                ]);
            }
        }

        Storage::delete($data['file']);

        Cache::forget("zaak.{$zaak->id}.documenten");

        activity('document')
            ->event('updated')
            ->causedBy(auth()->user())
            ->performedOn($zaak)
            ->withProperties([
                'document_uuid' => $documentUuid,
                'filename' => $data['file_name'],
                'titel' => $data['titel'],
            ])
            ->log(__('activity/event.updated'));
    }
}
