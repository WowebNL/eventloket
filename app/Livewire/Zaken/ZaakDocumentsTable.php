<?php

namespace App\Livewire\Zaken;

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Actions\NewDocumentVersionAction;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
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
    }

    #[On('refreshTable')]
    public function refresh(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->zaak->documenten->map(fn ($item) => $item->toArray()))
            ->columns([
                TextColumn::make('titel'),
                TextColumn::make('informatieobjecttype')
                    ->label(__('Type document'))
                    ->formatStateUsing(fn ($state) => $this->zaak->zaaktype->document_types->firstWhere('url', $state)->omschrijving),
                TextColumn::make('creatiedatum')
                    ->date('j M Y')
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
                    NewDocumentVersionAction::make($this->zaak),
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
            ->headerActions([
                UploadDocumentAction::make($this->zaak),
            ]);
    }

    public function render()
    {
        return view('livewire.shared.table');
    }
}
