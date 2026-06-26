<?php

namespace App\Filament\Shared\Resources\Zaken\Actions;

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Jobs\Zaak\UploadDocumentsJob;
use App\Models\Zaak;
use App\Support\Uploads\DocumentUploadType;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Woweb\Zgw\Facades\Zgw;

class UploadDocumentAction
{
    public static function make(Zaak $zaak): Action
    {
        return Action::make('upload')
            ->label(__('Nieuw bestand toevoegen'))
            ->icon('heroicon-o-arrow-up-tray')
            ->tooltip(__('Wanneer je voor de eerste keer een bestand toevoegt, kies dan voor "Nieuw bestand". Indien je eerder een bestand toegevoegd hebt en je wilt dit nogmaals toevoegen als een nieuwe versie, kies dan voor "Nieuwe versie"'))
            ->modalSubmitAction(fn (Action $action) => $action->label(__('Bestand(en) toevoegen')))
            ->schema(self::schema($zaak))
            ->modalAutofocus(false)
            ->authorize(fn (): bool => auth()->user()->can('uploadDocument', $zaak))
            ->action(function (array $data, Action $action) use ($zaak): void {
                $vertrouwelijkheidaanduiding = $data['vertrouwelijkheidaanduiding'] ?? match (auth()->user()->role) {
                    Role::Organiser => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
                    Role::Advisor => DocumentVertrouwelijkheden::Vertrouwelijk->value,
                    default => DocumentVertrouwelijkheden::Vertrouwelijk->value,
                };

                $fileNames = (array) ($data['file_names'] ?? []);
                $metadata = array_values((array) ($data['document_metadata'] ?? []));

                // In production Filament stores files before firing the action, so
                // $data['files'] holds the final storage paths. In test context the
                // FileUpload state is bypassed, so we fall back to the path stored
                // per-entry in the repeater (set explicitly in tests).
                $storedPaths = array_values(array_filter((array) ($data['files'] ?? [])));
                if (empty($storedPaths)) {
                    $storedPaths = array_values(array_filter(
                        array_map(fn (array $entry) => (string) ($entry['path'] ?? ''), $metadata),
                    ));
                }

                $fileData = [];
                foreach ($storedPaths as $index => $path) {
                    $meta = $metadata[$index] ?? [];
                    $fileData[] = [
                        'path' => $path,
                        'titel' => (string) ($meta['titel'] ?? ''),
                        'original_name' => $fileNames[$path] ?? basename($path),
                        'informatieobjecttype' => (string) ($meta['informatieobjecttype'] ?? ''),
                    ];
                }

                $fileData = array_filter($fileData, fn (array $f) => $f['path'] !== '');

                UploadDocumentsJob::dispatch(
                    $zaak,
                    array_values($fileData),
                    $vertrouwelijkheidaanduiding,
                    (int) auth()->id(),
                );

                $count = count($fileData);
                Notification::make()
                    ->title(trans_choice(
                        '{1} Document wordt verwerkt op de achtergrond.|[2,*] :count documenten worden verwerkt op de achtergrond.',
                        $count,
                        ['count' => $count],
                    ))
                    ->success()
                    ->send();

                $action->getLivewire()->dispatch('refreshTable');
            });
    }

    /** @return array<int, mixed> */
    public static function schema(Zaak $zaak): array
    {
        $fields = [];

        $fields[] = FileUpload::make('files')
            ->label(__('Bestanden'))
            ->required()
            ->multiple()
            ->maxSize(61440) // 60MB per file
            ->mimeTypeMap(config('app.document_mime_type_mappings'))
            ->rule(DocumentUploadType::fileUploadRule())
            ->directory('documents')
            ->visibility('private')
            ->storeFileNamesIn('file_names')
            ->live()
            ->afterStateUpdated(function (?array $state, Set $set, Get $get): void {
                if (empty($state)) {
                    $set('document_metadata', []);

                    return;
                }
                // afterStateUpdated fires with Livewire temp paths, not final stored paths.
                // Use the temp path only as a stable session key so metadata survives
                // re-renders. The actual file paths are read from $data['files'] in the action.
                $existing = collect($get('document_metadata') ?? [])->keyBy('_temp_path');
                $entries = array_map(function ($file) use ($existing): array {
                    $isTemp = $file instanceof TemporaryUploadedFile;
                    $tempPath = $isTemp ? $file->getRealPath() : (string) $file;
                    $originalName = $isTemp ? $file->getClientOriginalName() : null;

                    return [
                        '_temp_path' => $tempPath,
                        'titel' => $existing->get($tempPath)['titel']
                            ?? ($originalName !== null ? pathinfo($originalName, PATHINFO_FILENAME) : ''),
                        'informatieobjecttype' => $existing->get($tempPath)['informatieobjecttype'] ?? null,
                    ];
                }, $state);
                $set('document_metadata', $entries);
            });

        $fields[] = Select::make('bulk_informatieobjecttype')
            ->label(__('Documenttype voor alle bestanden instellen'))
            ->options(fn () => $zaak->document_types->pluck('omschrijving', 'url')->toArray())
            ->placeholder(__('Kies een type om dit voor alle bestanden tegelijk in te stellen'))
            ->visible(fn (Get $get): bool => ! empty($get('document_metadata')))
            ->live()
            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                if ($state === null) {
                    return;
                }
                $set('document_metadata', array_map(
                    fn (array $entry) => [
                        ...$entry,
                        'informatieobjecttype' => $state,
                    ],
                    (array) ($get('document_metadata') ?? []),
                ));
            });

        $userRole = auth()->user()->role;
        if (in_array($userRole, [Role::Reviewer, Role::ReviewerMunicipalityAdmin, Role::MunicipalityAdmin, Role::Admin])) {
            $fields[] = Select::make('vertrouwelijkheidaanduiding')
                ->label(__('Wie mag dit document inzien?'))
                ->options(function () {
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
                ->visible(fn (Get $get): bool => ! empty($get('document_metadata')))
                ->required();
        }

        $fields[] = Repeater::make('document_metadata')
            ->label(__('Documenten'))
            ->schema([
                TextInput::make('titel')
                    ->label(__('Titel'))
                    ->required()
                    ->maxLength(255),
                Select::make('informatieobjecttype')
                    ->label(__('Type document'))
                    ->options(fn () => $zaak->document_types->pluck('omschrijving', 'url')->toArray())
                    ->required(),
                Hidden::make('_temp_path'),
                Hidden::make('path'),
            ])
            ->visible(fn (Get $get): bool => ! empty($get('document_metadata')))
            ->deletable(false)
            ->addable(false)
            ->reorderable(false)
            ->columns(2);

        return $fields;
    }

    /** @return array<int, mixed> */
    public static function singleFileSchema(Zaak $zaak): array
    {
        $fields = [
            Select::make('informatieobjecttype')
                ->label(__('Type document'))
                ->options(fn () => $zaak->document_types->pluck('omschrijving', 'url')->toArray())
                ->required(),
        ];

        $userRole = auth()->user()->role;
        if (in_array($userRole, [Role::Reviewer, Role::ReviewerMunicipalityAdmin, Role::Coordinator, Role::MunicipalityAdmin, Role::Admin])) {
            $fields[] = Select::make('vertrouwelijkheidaanduiding')
                ->label(__('Wie mag dit document inzien?'))
                ->options(function () {
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
                ->required();
        }

        $fields[] = FileUpload::make('file')
            ->label(__('Bestand'))
            ->required()
            ->maxSize(61440) // 60MB
            ->mimeTypeMap(config('app.document_mime_type_mappings'))
            ->rule(DocumentUploadType::fileUploadRule())
            ->directory('documents')
            ->visibility('private')
            ->storeFileNamesIn('file_name')
            ->live()
            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                if ($state === null) {
                    return;
                }
                if ($get('titel') !== null && $get('titel') !== '') {
                    return;
                }
                $fileName = $get('file_name');
                $name = is_string($fileName) ? $fileName : basename($state);
                $set('titel', pathinfo($name, PATHINFO_FILENAME));
            });

        $fields[] = TextInput::make('titel')
            ->label(__('Titel'))
            ->required()
            ->maxLength(255);

        return $fields;
    }

    /**
     * Upload a single document to OpenZaak and link it to the zaak.
     * Preserved for backward compatibility with tests and other callers.
     */
    public static function uploadDocument(array $data, Zaak $zaak): Informatieobject
    {
        $connection = Zgw::connection($zaak->zgwConnectionName());

        $vertrouwelijkheidaanduiding = $data['vertrouwelijkheidaanduiding'] ?? match (auth()->user()->role) {
            Role::Organiser => DocumentVertrouwelijkheden::Zaakvertrouwelijk->value,
            Role::Advisor => DocumentVertrouwelijkheden::Vertrouwelijk->value,
            default => DocumentVertrouwelijkheden::Vertrouwelijk->value,
        };

        $formaat = DocumentUploadType::determineFormaat($data['file'], $data['file_name'] ?? null);
        $bestandsnaam = DocumentUploadType::ensureFileNameHasExtension($data['file_name'] ?? '', $formaat);

        $informatieobject = new Informatieobject(...$connection->documenten()->enkelvoudiginformatieobjecten()->store([
            'bronorganisatie' => $zaak->openzaak->bronorganisatie,
            'creatiedatum' => now()->format('Y-m-d'),
            'vertrouwelijkheidaanduiding' => $vertrouwelijkheidaanduiding,
            'titel' => $data['titel'],
            'auteur' => auth()->user()->name,
            'taal' => 'dut',
            'bestandsnaam' => $bestandsnaam,
            'bestandsomvang' => Storage::size($data['file']),
            'formaat' => $formaat,
            'inhoud' => base64_encode(Storage::get($data['file'])),
            'informatieobjecttype' => $data['informatieobjecttype'],
            'indicatieGebruiksrecht' => false,
        ]));

        $connection->zaken()->zaakinformatieobjecten()->store([
            'zaak' => $zaak->openzaak->url,
            'informatieobject' => $informatieobject->url,
        ]);

        Storage::delete($data['file']);

        Cache::forget("zaak.{$zaak->id}.documenten");

        activity('document')
            ->event('created')
            ->causedBy(auth()->user())
            ->performedOn($zaak)
            ->withProperties([
                'document_uuid' => $informatieobject->uuid,
                'filename' => $informatieobject->bestandsnaam,
                'titel' => $informatieobject->titel,
                'vertrouwelijkheidaanduiding' => $informatieobject->vertrouwelijkheidaanduiding,
            ])
            ->log(__('activity/event.created'));

        return $informatieobject;
    }
}
