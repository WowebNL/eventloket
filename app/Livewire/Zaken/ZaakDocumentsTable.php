<?php

namespace App\Livewire\Zaken;

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Models\Zaak;
use App\ValueObjects\ZGW\Informatieobject;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Woweb\Openzaak\Openzaak;

class ZaakDocumentsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public Zaak $zaak;

    public function mount(Zaak $zaak): void
    {
        $this->zaak = $zaak;
        // dd((new Openzaak)->catalogi()->informatieobjecttypen()->getAll(['zaaktype' => $this->zaak->openzaak->zaaktype])->pluck('omschrijving', 'url')->toArray());
        // $this->zaak->documenten->map(function($item) {
        //     $trail = (new Openzaak)->get('https://open-zaak.vrzl-test.woweb.app/documenten/api/v1/enkelvoudiginformatieobjecten/' . $item->uuid . '/audittrail');
        //     dd($trail);
        // });
    }

    #[On('refreshTable')]
    public function refresh(): void {}

    public function table(Table $table): Table
    {
        return $table
            /** @phpstan-ignore-next-line */
            ->records(fn (): Collection => $this->zaak->documenten->map(fn ($item) => $item->toArray()))
            ->columns([
                TextColumn::make('titel'),
                TextColumn::make('creatiedatum')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('versie')
                    ->sortable(),
                TextColumn::make('auteur')
                    ->sortable(),
                TextColumn::make('bestandsnaam'),
            ])
            ->filters([
                // ...
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('Bekijken'))
                    ->url(fn (array $record): string => route('zaak.documents.view', [
                        'zaak' => $this->zaak->id,
                        'documentuuid' => $record['uuid'],
                        'type' => 'view',
                    ]))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                Action::make('downloaden')
                    // ->label(__('municipality/resources/zaak.actions.download.label'))
                    ->url(fn (array $record): string => route('zaak.documents.view', [
                        'zaak' => $this->zaak->id,
                        'documentuuid' => $record['uuid'],
                        'type' => 'download',
                    ]))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-down-tray'),
                ActionGroup::make([
                    Action::make('new-version')
                        ->label(__('Nieuwe versie toevoegen'))
                        ->icon('heroicon-o-plus-circle')
                        ->modalSubmitAction(fn (Action $action) => $action->label(__('Nieuwe versie toevoegen')))
                        ->schema(fn (array $record) => [
                            TextInput::make('titel')
                                ->label(__('Titel'))
                                ->required()
                                ->maxLength(255)
                                ->default($record['titel']),
                            FileUpload::make('file')
                                ->label(__('Bestand'))
                                ->required()
                                ->maxSize(20480) // 20MB
                                ->directory('documents')
                                ->visibility('private')
                                ->storeFileNamesIn('file_name'),
                        ])
                        ->modalAutofocus(false)
                        ->action(function (array $record, array $data): void {

                            $oz = new Openzaak;
                            $lockString = $oz->documenten()->enkelvoudiginformatieobjecten()->lock($record['uuid']);
                            $oz->documenten()->enkelvoudiginformatieobjecten()->patch($record['uuid'],
                                [
                                    'inhoud' => base64_encode(Storage::get($data['file'])),
                                    'titel' => $data['titel'],
                                    'auteur' => auth()->user()->name,
                                    'bestandsnaam' => $data['file_name'],
                                    'bestandsomvang' => Storage::size($data['file']),
                                    'formaat' => Storage::mimeType($data['file']),
                                    'lock' => $lockString,
                                ]);
                            // unlock file
                            $oz->documenten()->enkelvoudiginformatieobjecten()->unlock($record['uuid'], $lockString);

                            Notification::make()
                                ->title('Nieuwe versie is toegevoegd')
                                ->success()
                                ->send();

                            Storage::delete($data['file']);

                            Cache::forget("zaak.{$this->zaak->id}.documenten");

                            $this->dispatch('refreshTable');

                        }),
                    Action::make('audittrail')
                        ->label(__('Audit trail'))
                        ->icon('heroicon-o-clock')
                        ->schema(fn (array $record) => [
                            Livewire::make(ListDocumentAuditTrails::class, ['audittrail' => (new Openzaak)->documenten()->enkelvoudiginformatieobjecten()->audittrail($record['uuid'])])->key('audit-trail-'.$record['uuid']),
                        ])
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false),
                    Action::make('specific-version')
                        ->label(__('Specifieke versie opvragen'))
                        ->icon('heroicon-o-document')
                        ->schema(fn (array $record) => [
                            Select::make('version')
                                ->label(__('Versie'))
                                ->options(function (array $record) {
                                    $items = [];
                                    for ($i = 1; $i <= (int) $record['versie']; $i++) {
                                        $items[$i] = 'versie '.$i;
                                    }

                                    return $items;
                                })
                                ->required(),
                        ])
                        ->modalSubmitAction(fn (Action $action) => $action->label(__('Bestand bekijken')))
                        ->visible(fn (array $record): bool => (int) $record['versie'] > 1)
                        ->action(function (array $record, array $data): void {

                            $this->js("window.open('".route('zaak.documents.view', [
                                'zaak' => $this->zaak->id,
                                'documentuuid' => $record['uuid'],
                                'type' => 'view',
                                'version' => $data['version'],
                            ])."', '_blank')");

                        }),

                ])
                    ->label('Meer acties')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->visible(fn (): bool => auth()->user()->role != Role::Organiser),
            ])
            ->toolbarActions([
                Action::make('upload')
                    ->label(__('Nieuw bestand toevoegen'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalSubmitAction(fn (Action $action) => $action->label(__('Bestand toevoegen')))
                    ->schema([
                        TextInput::make('titel')
                            ->label(__('Titel'))
                            ->required()
                            ->maxLength(255),
                        Select::make('informatieobjecttype')
                            ->label(__('Type document'))
                            ->options(fn () => $this->zaak->zaaktype->document_types->pluck('omschrijving', 'url')->toArray())
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
                            ->directory('documents')
                            ->visibility('private')
                            ->storeFileNamesIn('file_name'),
                    ])
                    ->modalAutofocus(false)
                    ->action(function (array $data): void {

                        $oz = new Openzaak;
                        $informatieobject = new Informatieobject(...$oz->documenten()->enkelvoudiginformatieobjecten()->store([
                            'bronorganisatie' => $this->zaak->openzaak->bronorganisatie,
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
                        ]));

                        $oz->zaken()->zaakinformatieobjecten()->store([
                            'zaak' => $this->zaak->openzaak->url,
                            'informatieobject' => $informatieobject->url,
                        ]);

                        Notification::make()
                            ->title('Document is toegevoegd')
                            ->success()
                            ->send();

                        Storage::delete($data['file']);

                        Cache::forget("zaak.{$this->zaak->id}.documenten");

                        $this->dispatch('refreshTable');
                    }),
            ]);
    }

    public function render()
    {
        return view('livewire.shared.table');
    }
}
