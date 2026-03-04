<?php

namespace App\Filament\Advisor\Widgets;

use App\Enums\AdviceStatus;
use App\Filament\Advisor\Resources\Zaken\ZaakResource;
use App\Filament\Shared\Resources\Threads\Actions\AssignAction;
use App\Filament\Shared\Resources\Threads\Actions\AssignToSelfAction;
use App\Filament\Shared\Resources\Threads\Filters\AssignedFilter;
use App\Filament\Shared\Resources\Threads\Filters\UnreadMessagesFilter;
use App\Filament\Shared\Resources\Threads\Tables\Components\LatestMessageColumn;
use App\Filament\Shared\Resources\Threads\Tables\Components\UnreadMessagesColumn;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Filters\AdviceStatusFilter;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Filters\AdvisoryFilter;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Tables\AdviceThreadsTable;
use App\Models\Threads\AdviceThread;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class AdviceThreadInboxWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('resources/advice_thread.widgets.inbox.heading');
    }

    public function table(Table $table): Table
    {
        return AdviceThreadsTable::configure($table)
            /** @phpstan-ignore-next-line */
            ->query(fn (): Builder => AdviceThread::query()->advice()->where('advisory_id', Filament::getTenant()->id)->where('advice_status', '!=', AdviceStatus::Concept))
            ->columns([
                TextColumn::make('zaak.reference_data.naam_evenement')
                    ->label(__('resources/advice_thread.columns.event.label'))
                    ->description(fn (AdviceThread $record) => $record->zaak->public_id)
                    ->icon('heroicon-s-eye')
                    ->sortable()
                    ->url(fn (AdviceThread $record) => ZaakResource::getUrl('view', ['record' => $record->zaak])),
                TextColumn::make('zaak.organisation.name')
                    ->label(__('resources/advice_thread.columns.organisation.label'))
                    ->sortable(),
                TextColumn::make('zaak.zaaktype.municipality.name')
                    ->label(__('resources/advice_thread.columns.municipality.label'))
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('resources/advice_thread.columns.title.label'))
                    ->searchable(),
                TextColumn::make('advice_status')
                    ->label(__('resources/advice_thread.columns.advice_status.label'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('advice_due_at')
                    ->label(__('resources/advice_thread.columns.advice_due_at.label'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                UnreadMessagesColumn::make(),
                LatestMessageColumn::make(),
                TextColumn::make('assignedUsers.name')
                    ->label(__('resources/advice_thread.columns.assigned_users.label'))
                    ->badge(),
            ])
            ->recordActions([
                AssignToSelfAction::make(),
                AssignAction::make(),
                ViewAction::make()
                    ->url(fn (AdviceThread $record) => AdviceThreadResource::getUrl('view', ['record' => $record, 'zaak' => $record->zaak])),
            ])
            ->recordUrl(fn (AdviceThread $record) => AdviceThreadResource::getUrl('view', ['record' => $record, 'zaak' => $record->zaak]))
            ->defaultSort('unread_messages_count', 'desc')
            ->filters([
                AdviceStatusFilter::make(),
                AdvisoryFilter::make(),
                AssignedFilter::make(),
                UnreadMessagesFilter::make(),
            ])
            ->filtersFormColumns(3)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false);
    }
}
