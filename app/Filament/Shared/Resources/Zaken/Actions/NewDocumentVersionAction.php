<?php

namespace App\Filament\Shared\Resources\Zaken\Actions;

use App\Exceptions\DocumentVersionRejectedException;
use App\Models\Zaak;
use App\Support\Documents\DocumentVersionAuthorizer;
use App\Support\Uploads\DocumentUploadType;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Woweb\Zgw\Exceptions\ApiRequestException;
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
                try {
                    self::createNewDocumentVersion($record['uuid'], $data, $zaak);
                } catch (DocumentVersionRejectedException $e) {
                    self::handleRejectedVersion($e);

                    return;
                }

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
                ->storeFileNamesIn('file_name')
                // Only accept a fresh upload. The stored path is read and
                // deleted after submitting, so a path in the field's state
                // must never be taken as is.
                ->preventFilePathTampering(),
        ];
    }

    /**
     * Report a new version the Documenten API did not accept and tell the user
     * why, instead of letting the exception surface as a generic error.
     *
     * A 403 is a refused authorisation for this client (for example a missing
     * lock or update scope on the document's type), which only an administrator
     * of the ZGW connection can resolve, so the message says who to turn to.
     * Anything else gets a general message.
     */
    public static function handleRejectedVersion(DocumentVersionRejectedException $rejection): void
    {
        report($rejection);

        Notification::make()
            ->title(__('Nieuwe versie niet opgeslagen'))
            ->body($rejection->isForbidden()
                ? __('Het zaaksysteem geeft geen toestemming om een nieuwe versie van dit document te maken.').' '.self::forbiddenFollowUp()
                : __('Het zaaksysteem kon de nieuwe versie nu niet opslaan. Probeer het later opnieuw. Blijft dit gebeuren, neem dan contact op met de beheerder.'))
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Who can resolve a refused authorisation depends on the portal. An
     * organiser has no access to the connection and can only turn to the
     * municipality. Users of the other portals are pointed to the
     * administrator of the connection.
     */
    private static function forbiddenFollowUp(): string
    {
        if (Filament::getCurrentPanel()?->getId() === 'organiser') {
            return __('Neem contact op met de gemeente.');
        }

        return __('Vraag de beheerder van de zaaksysteemkoppeling dit na te kijken.');
    }

    public static function createNewDocumentVersion(string $documentUuid, array $data, Zaak $zaak)
    {
        // The stored upload only exists to be sent to the Documenten API, so it
        // is removed whether or not the API accepted the new version. A refused
        // lock or patch otherwise left the file behind on local storage.
        try {
            $connectionName = $zaak->zgwConnectionName();
            $connection = Zgw::connection($connectionName);
            $formaat = DocumentUploadType::determineFormaat($data['file'], $data['file_name'] ?? null);
            $bestandsnaam = DocumentUploadType::ensureFileNameHasExtension($data['file_name'] ?? '', $formaat);

            try {
                $lockString = $connection->documenten()->enkelvoudiginformatieobjecten()->lock($documentUuid);
            } catch (ApiRequestException|ConnectionException $e) {
                throw DocumentVersionRejectedException::fromFailure($e, $connectionName, $documentUuid, DocumentVersionRejectedException::STEP_LOCK);
            }

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
                        'status' => Informatieobject::STATUS_DEFINITIEF,
                    ]
                );
            } catch (ApiRequestException|ConnectionException $e) {
                throw DocumentVersionRejectedException::fromFailure($e, $connectionName, $documentUuid, DocumentVersionRejectedException::STEP_PATCH);
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

                    // A lock that stays behind blocks every later version of
                    // this document, so it has to be visible in error
                    // reporting and not only in the log.
                    report(DocumentVersionRejectedException::fromFailure($unlockError, $connectionName, $documentUuid, DocumentVersionRejectedException::STEP_UNLOCK));
                }
            }
        } finally {
            Storage::delete($data['file']);
        }

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
