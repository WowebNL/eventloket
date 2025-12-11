<?php

namespace App\Filament\Shared\Resources\Zaken\Actions;

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Models\Zaak;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Woweb\Openzaak\Openzaak;

class UploadDocumentAction
{
    public static function make(Zaak $zaak): Action
    {
        return Action::make('upload')
            ->label(__('Nieuw bestand toevoegen'))
            ->icon('heroicon-o-arrow-up-tray')
            ->modalSubmitAction(fn (Action $action) => $action->label(__('Bestand toevoegen')))
            ->schema(self::schema($zaak))
            ->modalAutofocus(false)
            ->action(function (array $data, Action $action) use ($zaak): void {
                self::uploadDocument($data, $zaak);

                Notification::make()
                    ->title('Document is toegevoegd')
                    ->success()
                    ->send();

                $action->getLivewire()->dispatch('refreshTable');
            });
    }

    public static function schema(Zaak $zaak): array
    {
        return [
            TextInput::make('titel')
                ->label(__('Titel'))
                ->required()
                ->maxLength(255),
            Select::make('informatieobjecttype')
                ->label(__('Type document'))
                ->options(fn () => $zaak->zaaktype->document_types->pluck('omschrijving', 'url')->toArray())
                ->required(),
            Select::make('vertrouwelijkheidaanduiding')
                ->label(__('Wie mag dit document inzien?'))
                ->options(function () {
                    /** @phpstan-ignore-next-line */
                    $vertrouwelijkheden = DocumentVertrouwelijkheden::fromUserRole(auth()->user()->role);
                    $rolesByVertrouwelijkheid = DocumentVertrouwelijkheden::listUserRoles();
                    $options = [];
                    foreach ($rolesByVertrouwelijkheid as $key => $roles) {
                        if (in_array($key, $vertrouwelijkheden)) {
                            $options[$key] = collect($roles)->map(fn (Role $role) => $role->getLabel())->join(', ');
                        }
                    }

                    return $options;
                })
                ->required(),
            FileUpload::make('file')
                ->label(__('Bestand'))
                ->required()
                ->maxSize(20480) // 20MB
                ->acceptedFileTypes(config('app.document_file_types'))
                ->directory('documents')
                ->visibility('private')
                ->storeFileNamesIn('file_name'),
        ];
    }

    public static function uploadDocument(array $data, Zaak $zaak): Informatieobject
    {
        $oz = new Openzaak;
        $informatieobject = new Informatieobject(...$oz->documenten()->enkelvoudiginformatieobjecten()->store([
            'bronorganisatie' => $zaak->openzaak->bronorganisatie,
            'creatiedatum' => now()->format('Y-m-d'),
            'vertrouwelijkheidaanduiding' => $data['vertrouwelijkheidaanduiding'],
            'titel' => $data['titel'],
            'auteur' => auth()->user()->name,
            'taal' => 'dut',
            'bestandsnaam' => $data['file_name'],
            'bestandsomvang' => Storage::size($data['file']),
            'formaat' => Storage::mimeType($data['file']),
            'inhoud' => base64_encode(Storage::get($data['file'])),
            'informatieobjecttype' => $data['informatieobjecttype'],
            'indicatieGebruiksrecht' => false,
        ]));

        $oz->zaken()->zaakinformatieobjecten()->store([
            'zaak' => $zaak->openzaak->url,
            'informatieobject' => $informatieobject->url,
        ]);

        Storage::delete($data['file']);

        Cache::forget("zaak.{$zaak->id}.documenten");

        return $informatieobject;
    }
}
