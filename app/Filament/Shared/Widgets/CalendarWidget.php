<?php

namespace App\Filament\Shared\Widgets;

use App\Enums\Role;
use App\Filament\Exports\EventExporter;
use App\Filament\Shared\Resources\Zaken\Schemas\Components\RisicoClassificatiesSelect;
use App\Filament\Shared\Resources\Zaken\Schemas\ZaakInfolist;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Guava\Calendar\Filament\Actions\ViewAction;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use LogicException;

class CalendarWidget extends \Guava\Calendar\Filament\CalendarWidget
{
    use HasFilters;

    protected static bool $isDiscovered = false;

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    protected string|HtmlString|bool|null $heading = '';

    protected bool $eventClickEnabled = true;

    protected ?string $defaultEventClickAction = 'view';

    public function viewAction(): ViewAction
    {
        return ViewAction::make()
            ->modelLabel(__('resources/zaak.label'))
            ->pluralModelLabel(__('resources/zaak.plural_label'))
            ->extraModalFooterActions([
                Action::make('view')
                    ->label(__('shared/widgets/calendar.view_case'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Zaak $record): string => route('filament.municipality.resources.zaken.view', ['tenant' => Filament::getTenant(), 'record' => $record]))
                    ->color('primary')
                    ->button()
                    ->visible(fn () => in_array(auth()->user()->role, [Role::ReviewerMunicipalityAdmin, Role::MunicipalityAdmin, Role::Reviewer])),
            ]);
    }

    public function defaultSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema(ZaakInfolist::informationschema())
                ->columnSpan(8),
        ]);
    }

    public function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema($this->getFilterSchema())
                ->badge(fn () => count(array_filter($this->filters ?? [])))
                ->after(fn () => $this->dispatch('calendar--refresh')),
            ExportAction::make()
                ->exporter(EventExporter::class)
                ->label('Evenementen exporteren')
                ->modalHeading('Evenementen exporteren')
                ->columnMapping(false)
                ->fillForm(function (Component $livewire) {
                    if (! property_exists($livewire, 'filters')) {
                        throw new LogicException('The ['.$livewire::class.'] page must implement the ['.HasFilters::class.'] trait.');
                    }

                    return [
                        ...$livewire->filters,
                        'start_date' => now()->startOfMonth()->format('Y-m-d'),
                        'end_date' => now()->endOfMonth()->format('Y-m-d'),
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
                ]))),
        ];
    }

    // Let child widgets add their own filters.
    protected function getFilterSchema(): array
    {
        return [];
    }

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        $query = Event::query();

        return $this->applyContextFilters($query, $info);
    }

    // Let child widgets add their own constraints.
    protected function applyContextFilters(Builder $query, FetchInfo $info): Builder
    {
        $query->whereBetween('reference_data->start_evenement', [$info->start, $info->end]);

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
            ->preload();
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
                    return Zaak::query()
                        ->select(DB::raw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(reference_data, '$.status_name')) as status_name"))
                        ->pluck('status_name')
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
//                    ->orWhere('reference_data->indiener_naam', 'like', "%{$term}%") // TODO: Implement when indiener fields are added
//                    ->orWhere('reference_data->indiener_email', 'like', "%{$term}%")
                    ->orWhere('public_id', 'like', "%{$term}%")
                    ->orWhereHas('organisation', fn (Builder $oq) => $oq->where('name', 'like', "%{$term}%"));
            });
        }
    }

    protected function zaaktypesFilter()
    {
        return Select::make('zaaktypes')
            ->label(__('resources/zaak.columns.zaaktype.label'))
            ->options(fn () => Zaaktype::query()->orderBy('name')->pluck('name', 'id'))
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
