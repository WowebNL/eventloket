<?php

namespace App\Livewire\Zaken;

use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BesluitenInfolist extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    #[Locked]
    public Zaak $zaak;

    public function mount(Zaak $zaak): void
    {
        $this->zaak = $zaak;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->zaak)
            ->schema([
                RepeatableEntry::make('besluiten')
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
                                    ->formatStateUsing(fn ($state) => $this->zaak->zaaktype->document_types->firstWhere('url', $state)?->omschrijving),
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
        return view('livewire.shared.infolist');
    }
}
