<?php

namespace App\Livewire\Zaken;

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Actions\DownloadDocumentsAction;
use App\Filament\Shared\Resources\Zaken\Actions\NewDocumentVersionAction;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Models\Zaak;
use App\Services\Zgw\SubmissionDocumentDetector;
use App\ValueObjects\ZGW\Informatieobject;
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
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Woweb\Zgw\Facades\Zgw;

class ZaakDocumentsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public Zaak $zaak;

    /**
     * Read-only mode for the organiser when the bestanden tab is disabled for
     * the connection: only the files delivered with the application are shown
     * and no new files can be added.
     */
    #[Locked]
    public bool $submissionOnly = false;

    public bool $hasDocuments = false;

    public function mount(Zaak $zaak, bool $submissionOnly = false): void
    {
        $this->zaak = $zaak;
        $this->submissionOnly = $submissionOnly;
    }

    #[On('refreshTable')]
    public function refresh(): void {}

    /**
     * The documents shown in the table. In read-only submission mode only the
     * files the organiser delivered with the application are listed.
     *
     * @return Collection<int, Informatieobject>
     */
    private function records(): Collection
    {
        $documenten = $this->zaak->documenten;

        if ($this->submissionOnly) {
            return $documenten->filter(
                fn (Informatieobject $document) => SubmissionDocumentDetector::isSubmissionDocument($document, $this->zaak)
            )->values();
        }

        return $documenten;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->records()->mapWithKeys(fn ($item) => [$item->uuid => $item->toArray()]))
            ->defaultSort('created_at', direction: 'desc')
            ->columns([
                TextColumn::make('titel'),
                TextColumn::make('informatieobjecttype')
                    ->label(__('Type document'))
                    ->formatStateUsing(fn ($state) => $this->zaak->document_types->first(fn ($type) => (string) $type->url === $state)?->omschrijving),
                TextColumn::make('creatiedatum')
                    ->date(config('app.date_format'))
                    ->sortable(),
                TextColumn::make('versie')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()->role !== Role::Organiser),
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
                NewDocumentVersionAction::make($this->zaak)
                    ->visible(fn (): bool => ! $this->submissionOnly),
                ActionGroup::make([
                    Action::make('downloaden')
                    // ->label(__('municipality/resources/zaak.actions.download.label'))
                        ->url(fn (array $record): string => route('zaak.documents.view', [
                            'zaak' => $this->zaak->id,
                            'documentuuid' => $record['uuid'],
                            'type' => 'download',
                        ]))
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-arrow-down-tray'),
                    Action::make('audittrail')
                        ->label(__('Audit trail'))
                        ->icon('heroicon-o-clock')
                        ->schema(fn (array $record) => [
                            Livewire::make(ListDocumentAuditTrails::class, ['audittrail' => Zgw::connection($this->zaak->zgwConnectionName())->documenten()->enkelvoudiginformatieobjecten()->audittrail($record['uuid'])->all()])->key('audit-trail-'.$record['uuid']),
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
                UploadDocumentAction::make($this->zaak)
                    ->visible(fn (): bool => ! $this->submissionOnly),
            ])
            ->toolbarActions([
                DownloadDocumentsAction::make($this->zaak),
            ])
            ->emptyStateHeading('Een ogenblik geduld, de bestanden van de aanvraag komen zometeen beschikbaar...')
            ->emptyStateDescription(null);
    }

    public function render(): View
    {
        $this->hasDocuments = $this->zaak->documenten->isNotEmpty();

        return view('livewire.zaken.zaak-documents-table');
    }
}
