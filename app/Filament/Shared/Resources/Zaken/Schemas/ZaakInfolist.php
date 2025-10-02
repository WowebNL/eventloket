<?php

namespace App\Filament\Shared\Resources\Zaken\Schemas;

use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\Schemas\Components\LocationsTab;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\AdviceThreadRelationManager;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\OrganiserThreadsRelationManager;
use App\Livewire\Zaken\ZaakDocumentsTable;
use App\Models\Zaak;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Woweb\Openzaak\Openzaak;

class ZaakInfolist
{
    public static function informationschema(): array
    {
        return [
            TextEntry::make('reference_data.naam_evenement')
                ->label(__('resources/zaak.columns.naam_evenement.label')),
            TextEntry::make('public_id')
                ->icon('heroicon-o-identification')
                ->label(__('resources/zaak.columns.public_id.label')),
            TextEntry::make('zaaktype.name')
                ->label(__('resources/zaak.columns.zaaktype.label')),
            TextEntry::make('reference_data.risico_classificatie')
                ->label(__('resources/zaak.columns.risico_classificatie.label')),
            TextEntry::make('municipality.name')
                ->label(__('Ingediend bij gemeente')),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        $formBesluittypen = [];

        return $schema
            ->components([
                Grid::make()
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('municipality/resources/zaak.infolist.sections.information.label'))
                            ->description(__('municipality/resources/zaak.infolist.sections.information.description'))
                            ->columns(2)
                            ->schema(array_merge(self::informationschema(), [
                                TextEntry::make('reference_data.organisator')
                                    ->label(__('municipality/resources/zaak.columns.organisator.label')),
                                TextEntry::make('openzaak.uiterlijkeEinddatumAfdoening')
                                    ->date('d-m-Y')
                                    ->label(__('municipality/resources/zaak.columns.uiterlijkeEinddatumAfdoening.label')),
                            ]))
                            ->columnSpan(8),
                        Section::make(__('municipality/resources/zaak.infolist.sections.actions.label'))
                            ->description(__('municipality/resources/zaak.infolist.sections.actions.description'))
                            ->schema([
                                TextEntry::make('reference_data.risico_classificatie')
                                    ->label(__('resources/zaak.columns.risico_classificatie.label'))
                                    ->afterLabel(Schema::end([
                                        Icon::make('heroicon-o-pencil-square'),
                                        Action::make('editRisicoClassificatie')
                                            ->label(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_risico_classificatie.label'))
                                            ->fillForm(function (Zaak $record): array {
                                                /** @var ZaakReferenceData $referenceData */
                                                $referenceData = $record->reference_data;

                                                return [
                                                    'risico_classificatie' => $referenceData->risico_classificatie,
                                                ];
                                            })
                                            ->schema([
                                                Select::make('risico_classificatie')
                                                    ->label(__('resources/zaak.columns.risico_classificatie.label'))
                                                    ->options([
                                                        '0' => '0',
                                                        'A' => 'A',
                                                        'B' => 'B',
                                                        'C' => 'C',
                                                    ])->required(),
                                            ])
                                            ->action(function ($data, $record) {
                                                $eigenschap = null;
                                                foreach ($record->openzaak->eigenschappen as $item) {
                                                    if ($item->naam === 'risico_classificatie') {
                                                        $eigenschap = $item;
                                                        break;
                                                    }
                                                }
                                                if ($eigenschap) {
                                                    $openzaak = new Openzaak;
                                                    $openzaak->zaken()->zaken()->zaakeigenschappen($record->openzaak->uuid)->patch($eigenschap->uuid, [
                                                        'waarde' => $data['risico_classificatie'],
                                                    ]);

                                                    // update local reference for dispaying the new value immidiately
                                                    $record->reference_data = new ZaakReferenceData(...array_merge($record->reference_data->toArray(), ['risico_classificatie' => $data['risico_classificatie']]));
                                                    $record->save();

                                                    Notification::make()
                                                        ->success()
                                                        ->title(__('Risico classificatie is gewijzigd'))
                                                        ->send();
                                                } else {
                                                    Notification::make()
                                                        ->danger()
                                                        ->title(__('Er is iets misgegaan bij het wijzigen van de risico classificatie'))
                                                        ->send();
                                                }

                                            }),
                                    ])),
                                TextEntry::make('reference_data.status_name')
                                    ->label(__('resources/zaak.columns.status.label')),
                            ])->columnSpan(4),
                        Tabs::make('Tabs')
                            ->persistTabInQueryString()
                            ->tabs([
                                Tab::make('documents')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.documents.label'))
                                    ->icon('heroicon-o-document')
                                    ->schema([
                                        Livewire::make(ZaakDocumentsTable::class, ['zaak' => $schema->model])->key('documents-table-'.($schema->model->id ?? 'new')),
                                    ]),
                                Tab::make('Organisatievragen')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.messages.label'))
                                    ->icon('heroicon-o-chat-bubble-left')
                                    ->visible(fn (Zaak $record) => Filament::getCurrentPanel()->getId() === 'municipality')
                                    ->badge(function (Zaak $record) {
                                        $count = auth()->user()
                                            ->unreadMessages()
                                            ->whereHas('thread', fn ($query) => $query->organiser()->where('zaak_id', $record->id))
                                            ->count();

                                        return $count > 0 ? $count : null;
                                    })
                                    ->schema([
                                        Livewire::make(OrganiserThreadsRelationManager::class, fn (Zaak $record) => ['ownerRecord' => $record, 'pageClass' => ViewZaak::class]),
                                    ]),
                                Tab::make('advice_requests')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.advice_requests.label'))
                                    ->icon('heroicon-o-question-mark-circle')
                                    ->badge(function (Zaak $record) {
                                        $count = auth()->user()
                                            ->unreadMessages()
                                            ->whereHas('thread', fn ($query) => $query->advice()->where('zaak_id', $record->id))
                                            ->count();

                                        return $count > 0 ? $count : null;
                                    })
                                    ->schema([
                                        Livewire::make(AdviceThreadRelationManager::class, fn (Zaak $record) => ['ownerRecord' => $record, 'pageClass' => ViewZaak::class]),
                                    ]),
                                LocationsTab::make(),
                                Tab::make('log')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.log.label'))
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
