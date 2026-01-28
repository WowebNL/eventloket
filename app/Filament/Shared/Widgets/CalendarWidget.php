<?php

namespace App\Filament\Shared\Widgets;

use App\Enums\Role;
use App\Filament\Shared\Exports\AdvisorEventExporter;
use App\Filament\Shared\Exports\BaseEventExporter;
use App\Filament\Shared\Exports\ExtendedEventExporter;
use App\Filament\Shared\Resources\Zaken\Schemas\Components\LocationsTab;
use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Resources\Zaken\Schemas\ZaakInfolist;
use App\Filament\Shared\Resources\Zaken\Tables\ZakenTable;
use App\Models\Advisory;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Guava\Calendar\Concerns\CanRefreshCalendar;
use Guava\Calendar\Filament\Actions\ViewAction;
use Guava\Calendar\ValueObjects\DatesSetInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;

class CalendarWidget extends \Guava\Calendar\Filament\CalendarWidget implements Tables\Contracts\HasTable
{
    use CanRefreshCalendar;
    use HasFilters;
    use Tables\Concerns\InteractsWithTable {
        Tables\Concerns\InteractsWithTable::normalizeTableFilterValuesFromQueryString insteadof HasFilters;
    }

    #[Url]
    public ?string $viewtype = null;

    protected string $view = 'filament.shared.widgets.calendar-widget';

    protected static bool $isDiscovered = false;

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    protected string|HtmlString|bool|null $heading = '';

    protected bool $eventClickEnabled = true;

    protected bool $datesSetEnabled = true;

    protected ?string $defaultEventClickAction = 'view';

    public ?CarbonImmutable $start = null;

    public ?CarbonImmutable $end = null;

    public string $viewMode = 'calendar'; // 'calendar' or 'table'

    public function mount()
    {
        if (in_array($this->viewtype, ['calendar', 'table'])) {
            $this->viewMode = $this->viewtype;

            if ($this->viewMode === 'table') {
                $this->start = CarbonImmutable::parse(now()->startOfDay());
                $this->end = null;
            }
        }
    }

    protected function onDatesSet(DatesSetInfo $info): void
    {
        $this->start = $info->view->currentStart;
        $this->end = $info->view->currentEnd;
        $this->js("document.querySelector('.ec-today').innerText = 'Vandaag'"); // quickfix to translate 'Today' button
    }

    public function viewAction(): ViewAction
    {
        return ViewAction::make()
            ->modelLabel(__('resources/zaak.label'))
            ->pluralModelLabel(__('resources/zaak.plural_label'))
            ->before(function (Zaak $record) {
                activity('views')
                    ->event('view')
                    ->performedOn($record)
                    ->withProperties(array_filter([
                        'url' => request()->fullUrl(),
                        'type' => 'agenda_item_modal',
                    ]))
                    ->log('Viewed record in modal');
            })
            ->extraModalFooterActions([
                $this->viewActionFooterAction(),
            ]);
    }

    public function viewActionFooterAction()
    {
        return Action::make('view')
            ->label(__('shared/widgets/calendar.view_case'))
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(function (Zaak $record): string {
                $currentPanelId = Filament::getCurrentPanel()?->getId();

                if ($currentPanelId === 'advisor') {
                    return route('filament.advisor.resources.zaken.view', ['tenant' => Filament::getTenant(), 'record' => $record]);
                }

                return route('filament.municipality.resources.zaken.view', ['tenant' => $record->municipality, 'record' => $record]);
            })
            ->color('primary')
            ->button()
            ->visible(function (Zaak $record) {
                $user = auth()->user();

                if ($user instanceof MunicipalityUser) {
                    return $user->canAccessMunicipality($record->zaaktype->municipality_id);
                }

                if ($user instanceof AdvisorUser) {
                    /** @var Advisory $tenant */
                    $tenant = Filament::getTenant();

                    return $tenant->can_view_any_zaak;
                }

                return false;
            });
    }

    public function defaultSchema(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('event-modal-tabs')
                ->tabs([
                    Tabs\Tab::make('details')
                        ->label(__('Details'))
                        ->schema(ZaakInfolist::informationschema())
                        ->columns(2),
                    LocationsTab::make(),
                    Tabs\Tab::make('imported')
                        ->label(__('Geïmporteerde informatie'))
                        ->visible(fn (Zaak $record) => ! empty($record->imported_data))
                        ->schema([
                            KeyValueEntry::make('imported_data')
                                ->hiddenLabel()
                                ->keyLabel(__('Sleutel'))
                                ->valueLabel(__('Waarde'))
                                ->columns(1),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public function getHeaderActions(): array
    {
        $exporter = match (auth()->user()->role) {
            Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer => ExtendedEventExporter::class,
            Role::Advisor => AdvisorEventExporter::class,
            default => BaseEventExporter::class,
        };

        return [
            Action::make('toggleView')
                ->label(fn () => $this->viewMode === 'calendar' ? 'Lijst weergave' : 'Kalender weergave')
                ->icon(fn () => $this->viewMode === 'calendar' ? 'heroicon-o-list-bullet' : 'heroicon-o-calendar')
                ->action(fn () => $this->viewMode = $this->viewMode === 'calendar' ? 'table' : 'calendar')
                ->after(function () {
                    if ($this->viewMode === 'table') {
                        $this->start = CarbonImmutable::parse(now()->startOfDay());
                        $this->end = null;
                    }
                }),
            FilterAction::make()
                ->schema($this->getFilterSchema())
                ->badge(fn () => count(array_filter($this->filters ?? [])))
                ->after(function () {
                    if ($this->viewMode === 'calendar') {
                        $this->refreshRecords();
                    } else {
                        // Reset table to refresh with new filters
                        $this->resetTable();
                    }
                }),
            ExportAction::make()
                ->exporter($exporter)
                ->label('Evenementen exporteren')
                ->modalHeading('Evenementen exporteren')
                ->columnMapping(false)
                ->fillForm(function (Component $livewire) {
                    if (! property_exists($livewire, 'filters')) {
                        throw new LogicException('The ['.$livewire::class.'] page must implement the ['.HasFilters::class.'] trait.');
                    }

                    return [
                        ...($livewire->filters ?? []),
                        'start_date' => $this->start?->format('Y-m-d'),
                        'end_date' => $this->end?->format('Y-m-d'),
                    ];
                })
                ->schema([
                    Section::make('Filters')
                        ->schema([
                            $this->municipalitiesFilter(),
                            $this->zaaktypesFilter(),
                            $this->statusNameFilter(),
                            $this->organisationsFilter(),
                            RisicoClassificatiesSelect::make(),
                            $this->searchFilter(),
                        ]),
                    Section::make()
                        ->description('Exporteer evenementen in deze periode')
                        ->columns(2)
                        ->schema([
                            DatePicker::make('start_date')
                                ->label('Start datum')
                                ->required(),
                            DatePicker::make('end_date')
                                ->label('Eind datum')
                                ->required()
                                ->after('start_date'),
                        ]),

                ])
                ->modifyQueryUsing(fn (Builder $query, array $options) => $this->applyContextFilters($query, new FetchInfo([
                    'startStr' => $options['start_date'],
                    'endStr' => $options['end_date'],
                ])))
                ->extraModalFooterActions([
                    Action::make('exportToGeojson')
                        ->visible(fn () => in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Advisor, Role::Admin]))
                        ->label('Exporteer naar GeoJSON')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->action(function (array $mountedActions) {
                            $data = $mountedActions[0]->getRawData();
                            $filters = $data;
                            unset($filters['start_date'], $filters['end_date']);

                            $query = auth()->user()->role === Role::Advisor ? Event::query() : Zaak::query();
                            $events = $this->applyContextFilters(
                                $query,
                                new FetchInfo([
                                    'startStr' => $data['start_date'],
                                    'endStr' => $data['end_date'],
                                ]),
                            )->get();

                            $features = $events->map(function (Model $zaak) {
                                /** @var Zaak $zaak */
                                $geometry = $zaak->openzaak->zaakgeometrie;
                                if ($geometry) {
                                    return [
                                        'type' => 'Feature',
                                        'geometry' => $geometry,
                                        'properties' => $zaak->toArray(), // change this
                                    ];
                                }

                                return null;
                            })->filter();
                            $geojson = json_encode([
                                'type' => 'FeatureCollection',
                                'features' => $features->values()->toArray(),
                            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                            $filename = 'export_evenementen_'.now()->format('Y-m-d_H-i-s').'.geojson';

                            file_put_contents(storage_path('app/public/'.$filename), $geojson);

                            return response()->streamDownload(function () use ($geojson) {
                                echo $geojson;
                            }, $filename, ['Content-Type' => 'application/geo+json']);
                        })
                        ->color('primary')
                        ->button(),
                ]),
        ];
    }

    // Let child widgets add their own filters.
    protected function getFilterSchema(): array
    {
        return [];
    }

    // Table configuration for list view
    public function table(Table $table): Table
    {
        return ZakenTable::configure($table)
            ->query($this->getEvents())
            ->defaultSort('reference_data->start_evenement_datetime')
            ->recordActions([
                \Filament\Actions\ViewAction::make()
                    ->schema(fn (Schema $schema) => $this->defaultSchema($schema))
                    ->extraModalFooterActions([
                        $this->viewActionFooterAction(),
                    ]),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->deferFilters(false)
            ->filters([
                Filter::make('range')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('shared/widgets/calendar.filters.range.from.label'))
                            ->placeholder(__('shared/widgets/calendar.filters.range.from.placeholder'))
                            ->native(false)
                            ->closeOnDateSelection()
                            ->format(config('app.date_format'))
                            ->displayFormat(config('app.date_format'))
                            ->default(fn () => $this->start)
                            ->afterStateUpdated(fn ($state) => $this->start = $state ? CarbonImmutable::parse($state) : null),
                        DatePicker::make('to')
                            ->label(__('shared/widgets/calendar.filters.range.to.label'))
                            ->placeholder(__('shared/widgets/calendar.filters.range.to.placeholder'))
                            ->native(false)
                            ->closeOnDateSelection()
                            ->format(config('app.date_format'))
                            ->displayFormat(config('app.date_format'))
                            ->default(fn () => $this->end)
                            ->afterStateUpdated(fn ($state) => $this->end = $state ? CarbonImmutable::parse($state) : null),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('reference_data->start_evenement', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('reference_data->start_evenement', '<=', $date),
                            );
                    }),
            ]);
    }

    protected function getEvents(?FetchInfo $info = null): Collection|array|Builder
    {
        $query = Event::query();

        return $this->applyContextFilters($query, $info);
    }

    // Let child widgets add their own constraints.
    protected function applyContextFilters(Builder $query, ?FetchInfo $info = null): Builder
    {
        if ($info) {
            $query->whereBetween('reference_data->start_evenement', [$info->start, $info->end]);
        }

        $filters = $this->filters ?? [];

        $this->applyMunicipalitiesFilter($query, $filters);

        $this->applyStatusNameFilter($query, $filters);

        $this->applyOrganisationsFilter($query, $filters);

        $this->applySearchFilter($query, $filters);

        $this->applyRisicoClassificatiesFilter($query, $filters);

        $this->applyZaaktypesFilter($query, $filters);

        return $query;
    }

    protected function municipalitiesFilter()
    {
        return Select::make('municipalities')
            ->label(__('admin/resources/municipality.plural_label'))
            ->options(fn () => Municipality::query()->orderBy('name')->pluck('name', 'id'))
            ->multiple()
            ->searchable()
            ->preload();
    }

    protected function applyMunicipalitiesFilter(Builder $query, array $filters)
    {
        if (! empty($filters['municipalities'])) {
            $query->whereHas('zaaktype', fn (Builder $q) => $q->whereIn('municipality_id', $filters['municipalities']));
        }
    }

    protected function organisationsFilter()
    {
        return Select::make('organisations')
            ->label(__('admin/resources/organisation.plural_label'))
            ->options(fn () => Organisation::query()->orderBy('name')->pluck('name', 'id'))
            ->multiple()
            ->searchable()
            ->preload()
            ->visible(fn () => auth()->user()->role !== Role::Organiser);
    }

    protected function applyOrganisationsFilter(Builder $query, array $filters)
    {
        if (! empty($filters['organisations'])) {
            $query->whereIn('organisation_id', $filters['organisations']);
        }
    }

    protected function statusNameFilter()
    {
        return Select::make('reference_data.status_name')
            ->label('Status')
            ->options(function () {
                return Cache::remember('zaak_status_name_options', 60 * 60 * 24, function () {
                    return Zaak::all()
                        ->pluck('reference_data.status_name')
                        ->unique()
                        ->sort()
                        ->mapWithKeys(fn ($status_name) => [$status_name => $status_name]);
                });
            })
            ->multiple()
            ->searchable()
            ->preload();
    }

    protected function applyStatusNameFilter(Builder $query, array $filters)
    {
        if (! empty($filters['reference_data.status_name'])) {
            $query->whereIn('reference_data->status_name', $filters['reference_data.status_name']);
        }
    }

    protected function applyRisicoClassificatiesFilter(Builder $query, array $filters)
    {
        if (! empty($filters['risico_classificaties'])) {
            $query->whereIn('reference_data->risico_classificatie', $filters['risico_classificaties']);
        }
    }

    protected function searchFilter()
    {
        return TextInput::make('search')
            ->label(__('Search'))
            ->helperText('Zoek op evenement naam of naam van de indiener');
    }

    protected function applySearchFilter(Builder $query, array $filters)
    {
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);

            $query->where(function (Builder $q) use ($term) {
                $q->where('reference_data->naam_evenement', 'like', "%{$term}%")
                    ->orWhere('public_id', 'like', "%{$term}%");
            });
            if (auth()->user()->role !== Role::Organiser) {
                $query
                    ->orWhere('reference_data->organisator', 'like', "%{$term}%")
                    ->orWhereHas('organisation', fn (Builder $oq) => $oq->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('organiserUser', fn (Builder $ouq) => $ouq->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
            }
        }
    }

    protected function zaaktypesFilter()
    {
        return Select::make('zaaktypes')
            ->label(__('resources/zaak.columns.zaaktype.label'))
            ->options(function () {
                $query = Zaaktype::query()->orderBy('name');

                if (in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer])) {
                    /** @var Municipality $municipality */
                    $municipality = Filament::getTenant();
                    $query->where('municipality_id', $municipality->id);
                }

                return $query->pluck('name', 'id');
            })
            ->multiple()
            ->searchable()
            ->preload();
    }

    protected function applyZaaktypesFilter(Builder $query, array $filters)
    {
        if (! empty($filters['zaaktypes'])) {
            $query->whereIn('zaaktype_id', $filters['zaaktypes']);
        }
    }
}
