<?php

namespace App\Filament\Shared\Resources\Zaken\Schemas;

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\Schemas\Components\LocationsTab;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\AdviceThreadRelationManager;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\OrganiserThreadsRelationManager;
use App\Livewire\Zaken\BesluitenInfolist;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
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
            TextEntry::make('reference_data.organisator')
                ->label(__('municipality/resources/zaak.columns.organisator.label'))
                ->visible(fn () => in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer])),
            TextEntry::make('organisation.phone')
                ->label(__('resources/zaak.columns.telefoon.label'))
                ->visible(fn (?string $state) => ! empty($state)),
            TextEntry::make('organiseruser.phone')
                ->label(__('resources/zaak.columns.telefoon-organiser.label'))
                ->visible(fn ($state) => ! empty($state)),
            TextEntry::make('organisation.email')
                ->label(__('resources/zaak.columns.email.label'))
                ->visible(fn (?string $state) => ! empty($state)),
            TextEntry::make('organiserUser.email')
                ->label(__('resources/zaak.columns.email-organiser.label'))
                ->visible(fn (?string $state) => ! empty($state)),
            TextEntry::make('reference_data.start_evenement')
                ->dateTime(config('app.datetime_format'))
                ->label(__('resources/zaak.columns.start_evenement.label')),
            TextEntry::make('reference_data.eind_evenement')
                ->dateTime(config('app.datetime_format'))
                ->label(__('resources/zaak.columns.eind_evenement.label')),
        ];
    }

    public static function resultaatSection(): Section
    {
        return Section::make(__('Resultaat'))
            ->description(__('Het resultaat van deze zaak is vastgesteld.'))
            ->columns(2)
            ->schema([
                TextEntry::make('openzaak.resultaattype.omschrijving')
                    ->label(__('Resultaat')),
                TextEntry::make('openzaak.resultaat.toelichting')
                    ->label(__('Toelichting op het resultaat'))
                    ->visible(fn (Zaak $record) => $record->openzaak->resultaat && Arr::has($record->openzaak->resultaat, 'toelichting') && $record->openzaak->resultaat['toelichting']),
                TextEntry::make('openzaak.status_name')
                    ->label(__('Huidige status')),
                TextEntry::make('openzaak.status.datumStatusGezet')
                    ->label(__('Status gezet op'))
                    ->date(config('app.date_format')),
            ])
            ->columnSpan(4)
            ->visible(fn (Zaak $record) => $record->openzaak->resultaat);
    }

    public static function configure(Schema $schema): Schema
    {
        /** @var Zaak $zaak */
        $zaak = $schema->model;

        return $schema
            ->components(array_filter([
                $zaak->reference_data->resultaat ? new HtmlString(Blade::render('filament.components.zaak-result', ['resultaat' => $zaak->reference_data->resultaat])) : null,
                Grid::make()
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('municipality/resources/zaak.infolist.sections.information.label'))
                            ->description(__('municipality/resources/zaak.infolist.sections.information.description'))
                            ->columns(2)
                            ->schema(array_merge(self::informationschema(), [
                                TextEntry::make('openzaak.uiterlijkeEinddatumAfdoening')
                                    ->date(config('app.date_format'))
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
                            ])
                            ->columnSpan(4)
                            ->hidden(fn (Zaak $record) => $record->reference_data->resultaat),
                        self::resultaatSection(),
                        Tabs::make('Tabs')
                            ->persistTabInQueryString()
                            ->tabs([
                                Tab::make('besluiten')
                                    ->label(__('municipality/resources/zaak.infolist.tabs.decisions.label'))
                                    ->icon('heroicon-o-briefcase')
                                    ->schema([
                                        Livewire::make(BesluitenInfolist::class, ['zaak' => $schema->model])->key('besluiten-table-'.($schema->model->id ?? 'new')),
                                    ])
                                    ->visible(fn (Zaak $record) => $record->besluiten->count() > 0),
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
                                    ->schema([]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]));
    }
}
