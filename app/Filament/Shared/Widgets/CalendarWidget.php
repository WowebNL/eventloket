<?php

namespace App\Filament\Shared\Widgets;

use App\Filament\Municipality\Resources\Zaken\Schemas\ZaakInfolist;
use App\Models\Zaak;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Schemas\Schema;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class CalendarWidget extends \Guava\Calendar\Filament\CalendarWidget
{
    use HasFilters;

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    protected string|HtmlString|bool|null $heading = '';

    protected bool $eventClickEnabled = true;

    protected ?string $defaultEventClickAction = 'view';

    public function defaultSchema(Schema $schema): Schema
    {
        return ZaakInfolist::configure($schema);
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
