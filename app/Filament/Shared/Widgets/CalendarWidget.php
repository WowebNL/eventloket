<?php

namespace App\Filament\Shared\Widgets;

use App\Enums\Role;
use App\Filament\Municipality\Resources\Zaken\Schemas\ZaakInfolist;
use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

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

    public function defaultSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('shared/widgets/calendar.modal_title'))
                ->columns(2)
                ->schema(ZaakInfolist::informationschema())
                ->columnSpan(8)
                ->footer([
                    Action::make('view')
                        ->label(__('shared/widgets/calendar.view_case'))
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (Zaak $record): string => route('filament.municipality.resources.zaken.view', ['tenant' => Filament::getTenant(), 'record' => $record]))
                        ->color('primary')
                        ->button()
                        ->visible(fn () => in_array(auth()->user()->role, [Role::ReviewerMunicipalityAdmin, Role::MunicipalityAdmin, Role::Reviewer])),
                ]),

        ]);
    }

    public function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema($this->getFilterSchema())
                ->badge(fn () => count(array_filter($this->filters ?? [])))
                ->after(fn () => $this->dispatch('calendar--refresh')),
        ];
    }

    // Let child widgets add their own filters.
    protected function getFilterSchema(): array
    {
        return [];
    }

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        $query = Zaak::query();

        return $this->applyContextFilters($query, $info);
    }

    // Let child widgets add their own constraints.
    protected function applyContextFilters(Builder $query, FetchInfo $info): Builder
    {
        return $query;
    }
}
