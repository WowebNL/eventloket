<?php

namespace App\Livewire\Zaken;

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Actions\DownloadDocumentsAction;
use App\Filament\Shared\Resources\Zaken\Actions\NewDocumentVersionAction;
use App\Filament\Shared\Resources\Zaken\Actions\UploadDocumentAction;
use App\Models\Zaak;
use App\Services\Zgw\SubmissionDocumentDetector;
use App\ValueObjects\ZGW\Informatieobject;
use App\ValueObjects\ZGW\ZaakDocumentSet;
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

    /**
     * How many documents of this zaak could not be read. Drives the notice
     * above the table, so the reader is never shown a short list as if it were
     * the whole list.
     */
    public int $unreadableDocumentCount = 0;

    private ?ZaakDocumentSet $documentSet = null;

    public function mount(Zaak $zaak, bool $submissionOnly = false): void
    {
        $this->zaak = $zaak;
        $this->submissionOnly = $submissionOnly;
    }

    #[On('refreshTable')]
    public function refresh(): void {}

    /**
     * The documents read for this zaak, resolved once per request.
     *
     * The model caches an incomplete read only briefly, so two calls can give
     * two different answers; resolving once keeps the table, its empty state and
     * the notice above them describing one and the same read.
     */
    private function documents(): ZaakDocumentSet
    {
        return $this->documentSet ??= $this->zaak->documentenForDisplay();
    }

    /**
     * The documents shown in the table. In read-only submission mode only the
     * files the organiser delivered with the application are listed.
     *
     * @return Collection<int, Informatieobject>
     */
    private function records(): Collection
    {
        $documenten = $this->documents()->documenten;

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
                // Use hidden() rather than a second visible(): visible() would
                // replace the action's own visibility closure and discard the
                // DocumentVersionAuthorizer ownership check, making "Nieuwe
                // versie" appear for everyone. hidden() is a separate condition
                // that is AND-ed with that check.
                NewDocumentVersionAction::make($this->zaak)
                    ->hidden(fn (): bool => $this->submissionOnly),
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
            ->emptyStateHeading(fn (): string => $this->emptyStateHeading())
            ->emptyStateDescription(fn (): ?string => $this->emptyStateDescription());
    }

    /**
     * An empty table has four quite different causes, which used to be
     * indistinguishable: nothing has arrived from ZGW yet, everything that did
     * arrive is hidden by the visibility rules, (in submission mode) the zaak
     * only holds documents that were not part of the application, or the
     * documents API refused every document there is. Saying "hold on, the files
     * are coming" in the other three cases sends the reader waiting for
     * something that is never going to appear.
     */
    private function emptyStateHeading(): string
    {
        if ($this->nothingCouldBeRead()) {
            return __('resources/zaak.documents.unreadable.empty_state_heading');
        }

        if ($this->documents()->documenten->isNotEmpty()) {
            return __('Geen bestanden om te tonen');
        }

        return $this->zaakHasDocumentsInZgw()
            ? __('Geen bestanden om te tonen')
            : __('Een ogenblik geduld, de bestanden van de aanvraag komen zometeen beschikbaar...');
    }

    private function emptyStateDescription(): ?string
    {
        if ($this->nothingCouldBeRead()) {
            // The notice above the table already explains this one; repeating it
            // here would only say the same thing twice.
            return null;
        }

        if ($this->documents()->documenten->isNotEmpty()) {
            // Documents exist and are visible, but none of them belong to the
            // application itself.
            return __('Bij deze aanvraag zijn geen aanvraagdocumenten ingediend.');
        }

        return $this->zaakHasDocumentsInZgw()
            ? __('Deze zaak bevat wel bestanden, maar die zijn niet zichtbaar met uw rechten of hun status. Neem contact op met de beheerder als u ze wel zou moeten zien.')
            : null;
    }

    /**
     * Whether every document of this zaak was refused. Blocks the two empty
     * states that would then be untrue: "the files are still coming" (they are
     * already there) and "not visible with your rights" (rights are not what
     * went wrong here).
     */
    private function nothingCouldBeRead(): bool
    {
        return $this->documents()->isIncomplete() && $this->documents()->documenten->isEmpty();
    }

    /**
     * Whether the zaak holds any document at all, before the status and role
     * filters are applied. Distinguishes "nothing there yet" from "everything
     * filtered out".
     */
    private function zaakHasDocumentsInZgw(): bool
    {
        return $this->documents()->totalCount() > 0;
    }

    public function render(): View
    {
        $documents = $this->documents();

        $this->hasDocuments = $documents->documenten->isNotEmpty();
        $this->unreadableDocumentCount = $documents->unreadableCount;

        return view('livewire.zaken.zaak-documents-table');
    }
}
