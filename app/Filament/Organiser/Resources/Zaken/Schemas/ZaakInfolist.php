<?php

namespace App\Filament\Organiser\Resources\Zaken\Schemas;

use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\Schemas\ZaakInfolist as SchemasZaakInfolist;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\OrganiserThreadsRelationManager;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Zaak;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ZaakInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('organiser/resources/zaak.infolist.sections.information.label'))
                            ->description(__('organiser/resources/zaak.infolist.sections.information.description'))
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema(SchemasZaakInfolist::informationschema()),
                        Tabs::make('Tabs')
                            ->tabs([
                                Tab::make('documents')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.documents.label'))
                                    ->schema([
                                        Livewire::make(ZaakDocumentsTable::class, ['zaak' => $schema->model])->key('documents-table-'.($schema->model->id ?? 'new')),
                                    ]),
                                Tab::make('messages')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.messages.label'))
                                    ->schema([
                                        Livewire::make(OrganiserThreadsRelationManager::class, fn (Zaak $record) => ['ownerRecord' => $record, 'pageClass' => ViewZaak::class]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
