<?php

namespace App\Filament\Municipality\Resources\Zaken\Schemas;

use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Zaak;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ZaakInfolist
{
    public static function informationschema(): array
    {
        return [
            TextEntry::make('reference_data.naam_evenement')
                ->label(__('municipality/resources/zaak.columns.naam_evenement.label')),
            TextEntry::make('public_id')
                ->icon('heroicon-o-identification')
                ->label(__('municipality/resources/zaak.columns.public_id.label')),
            TextEntry::make('zaaktype.name')
                ->label(__('municipality/resources/zaak.columns.zaaktype.label')),
            TextEntry::make('zaakdata.zaakeigenschappen_key_value.risico_classificatie')
                ->label(__('Risico classificatie uit formulier')),
            TextEntry::make('municipality.name')
                ->label(__('Ingediend bij gemeente')),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Informatie')
                            ->description('Informatie over de zaak')
                            ->columns(2)
                            ->schema(self::informationschema())
                            ->columnSpan(8),
                        Section::make('Acties')
                            ->description('Voer wijzigingen uit binnen de zaak')
                            ->schema([
                                TextEntry::make('reference_data.risico_classificatie')
                                    ->label(__('municipality/resources/zaak.columns.risico_classificatie.label'))
                                    ->afterLabel(Schema::end([
                                        Icon::make('heroicon-o-pencil-square'),
                                        Action::make('editRisicoClassificatie')
                                            ->label(__('Wijzigen'))
                                            ->fillForm(function (Zaak $record): array {
                                                /** @var ZaakReferenceData $referenceData */
                                                $referenceData = $record->reference_data;

                                                return [
                                                    'risico_classificatie' => $referenceData->risico_classificatie,
                                                ];
                                            })
                                            ->schema([
                                                Select::make('risico_classificatie')
                                                    ->label(__('municipality/resources/zaak.columns.risico_classificatie.label'))
                                                    ->options([
                                                        '0' => '0',
                                                        'A' => 'A',
                                                        'B' => 'B',
                                                        'C' => 'C',
                                                    ])->required(),
                                            ])
                                            ->action(function ($data, $record) {
                                                // todo
                                            }),
                                    ])),
                                TextEntry::make('reference_data.status_name')
                                    ->label(__('municipality/resources/zaak.columns.status.label')),
                            ])->columnSpan(4),
                        Tabs::make('Tabs')
                            ->tabs([
                                Tab::make('Bestanden')
                                    ->schema([
                                        Livewire::make(ZaakDocumentsTable::class, ['zaak' => $schema->model])->key('documents-table-'.($schema->model->id ?? 'new')),
                                    ]),
                                Tab::make('Berichten')
                                    ->schema([
                                    ]),
                                Tab::make('Adviesvragen')
                                    ->schema([
                                    ]),
                                Tab::make('Locaties')
                                    ->schema([
                                    ]),
                                Tab::make('Log')
                                    ->schema([
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
