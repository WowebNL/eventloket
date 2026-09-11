<?php

namespace App\Livewire\Zaken;

use App\Models\Zaak;
use App\ValueObjects\ZGW\ZaakBesluitSet;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BesluitenInfolist extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    #[Locked]
    public Zaak $zaak;

    /**
     * How many besluit documents could not be read. Drives the notice above the
     * list, so a besluit that fell away because its document is missing is never
     * simply absent from the screen.
     */
    public int $unreadableDocumentCount = 0;

    private ?ZaakBesluitSet $besluitSet = null;

    public function mount(Zaak $zaak): void
    {
        $this->zaak = $zaak;
    }

    /**
     * The besluiten read for this zaak, resolved once per request.
     */
    private function besluiten(): ZaakBesluitSet
    {
        return $this->besluitSet ??= $this->zaak->besluitenForDisplay();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->zaak)
            ->schema([
                RepeatableEntry::make('besluiten')
                    // Read through the component rather than off the record: the
                    // record's own accessor fails on the first besluit document
                    // the API refuses, which is right for mail and jobs and wrong
                    // for a screen.
                    ->state(fn (): Collection => $this->besluiten()->besluiten)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Naam besluit')),
                        TextEntry::make('identificatie')
                            ->label(__('Identificatie')),
                        TextEntry::make('datum')
                            ->label(__('Datum besluit'))
                            ->date(config('app.date_format')),
                        TextEntry::make('toelichting')
                            ->label(__('Toelichting')),
                        TextEntry::make('ingangsdatum')
                            ->label(__('Ingangsdatum'))
                            ->date(config('app.date_format')),
                        TextEntry::make('vervaldatum')
                            ->label(__('Vervaldatum'))
                            ->date(config('app.date_format'))
                            ->visible(fn ($get) => ! empty($get('vervaldatum'))),
                        RepeatableEntry::make('besluitDocumenten')
                            ->table([
                                TableColumn::make('titel'),
                                TableColumn::make(__('Type document')),
                                TableColumn::make(__('Creatiedatum')),
                                TableColumn::make(__('Versie')),
                                TableColumn::make(__('Auteur')),
                                TableColumn::make(__('Bestandsnaam')),
                                TableColumn::make(__('Acties')),
                            ])
                            ->schema([
                                TextEntry::make('titel'),
                                TextEntry::make('informatieobjecttype')
                                    ->formatStateUsing(fn ($state) => $this->zaak->document_types->first(fn ($type) => (string) $type->url === $state)?->omschrijving),
                                TextEntry::make('creatiedatum')
                                    ->date(config('app.date_format')),
                                TextEntry::make('versie'),
                                TextEntry::make('auteur'),
                                TextEntry::make('bestandsnaam'),
                                TextEntry::make('uuid')
                                    ->extraAttributes(['style' => 'display: none;'])
                                    ->afterContent(
                                        Action::make('view')
                                            ->label(__('Bekijken'))
                                            ->url(fn ($state): string => route('zaak.documents.view', [
                                                'zaak' => $this->zaak->id,
                                                'documentuuid' => $state,
                                                'type' => 'view',
                                            ])
                                            )
                                            ->openUrlInNewTab()
                                            ->icon('heroicon-o-eye'),
                                    ),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public function render()
    {
        $this->unreadableDocumentCount = $this->besluiten()->unreadableDocumentCount;

        return view('livewire.zaken.besluiten-infolist');
    }
}
