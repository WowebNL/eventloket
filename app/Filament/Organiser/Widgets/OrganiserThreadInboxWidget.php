<?php

namespace App\Filament\Organiser\Widgets;

use App\Filament\Organiser\Resources\Zaken\ZaakResource;
use App\Filament\Shared\Resources\Threads\Filters\UnreadMessagesFilter;
use App\Filament\Shared\Resources\Threads\Tables\Components\LatestMessageColumn;
use App\Filament\Shared\Resources\Threads\Tables\Components\UnreadMessagesColumn;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\OrganiserThreadResource;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\Tables\OrganiserThreadsTable;
use App\Models\Threads\OrganiserThread;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class OrganiserThreadInboxWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('resources/organiser_thread.widgets.inbox.heading');
    }

    public function table(Table $table): Table
    {
        return OrganiserThreadsTable::configure($table)
            ->modelLabel(__('resources/organiser_thread.organiser_label'))
            ->pluralModelLabel(__('resources/organiser_thread.organiser_plural_label'))
            /** @phpstan-ignore-next-line */
            ->query(fn (): Builder => OrganiserThread::query()->organiser()->whereHas('zaak', fn (Builder $query) => $query->where('organisation_id', Filament::getTenant()->id)))
            ->columns([
                TextColumn::make('zaak.reference_data.naam_evenement')
                    ->label(__('resources/organiser_thread.columns.event.label'))
                    ->description(fn (OrganiserThread $record) => $record->zaak->public_id)
                    ->icon('heroicon-s-eye')
                    ->sortable()
                    ->url(fn (OrganiserThread $record) => ZaakResource::getUrl('view', ['record' => $record->zaak])),
                TextColumn::make('zaak.zaaktype.municipality.name')
                    ->label(__('resources/organiser_thread.columns.municipality.label'))
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('resources/organiser_thread.columns.title.label'))
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label(__('resources/organiser_thread.columns.created_by.label'))
                    ->sortable(),
                UnreadMessagesColumn::make(),
                LatestMessageColumn::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (OrganiserThread $record) => OrganiserThreadResource::getUrl('view', ['record' => $record, 'zaak' => $record->zaak])),
            ])
            ->recordUrl(fn (OrganiserThread $record) => OrganiserThreadResource::getUrl('view', ['record' => $record, 'zaak' => $record->zaak]))
            ->defaultSort('unread_messages_count', 'desc')
            ->filters([
                UnreadMessagesFilter::make()->name('unread_organiser'),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false);
    }
}
