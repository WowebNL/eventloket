<?php

namespace App\Filament\Shared\Widgets;

use App\Filament\Shared\Resources\Threads\Filters\UnreadMessagesFilter;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Filters\AdviceStatusFilter;
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

    protected function isMunicipality(): bool
    {
        return Filament::getCurrentPanel()->getId() === 'municipality';
    }

    protected function isAdvisor(): bool
    {
        return Filament::getCurrentPanel()->getId() === 'advisor';
    }

    protected function zaakResourceClass(): string
    {
        return match (Filament::getCurrentPanel()->getId()) {
            'municipality' => \App\Filament\Municipality\Resources\Zaken\ZaakResource::class,
            'advisor' => \App\Filament\Advisor\Resources\Zaken\ZaakResource::class,
            default => throw new \Exception('This panel is not supported'),
        };
    }

    public function table(Table $table): Table
    {
        return AdviceThreadsTable::configure($table)
            ->query(function (): Builder {
                $query = AdviceThread::query()->advice();

                return match (Filament::getCurrentPanel()->getId()) {
                    /** @phpstan-ignore-next-line */
                    'municipality' => $query->whereHas('zaak.zaaktype', fn (Builder $query) => $query->where('municipality_id', Filament::getTenant()->id)),
                    /** @phpstan-ignore-next-line */
                    'advisor' => $query->where('advisory_id', Filament::getTenant()->id),
                    default => throw new \Exception('This panel is not supported'),
                };
            })
            ->columns([
                TextColumn::make('zaak.reference_data.naam_evenement')
                    ->label(__('resources/advice_thread.columns.event.label'))
                    ->description(fn (AdviceThread $record) => $record->zaak->public_id)
                    ->icon('heroicon-s-eye')
                    ->sortable()
                    ->url(fn (AdviceThread $record) => $this->zaakResourceClass()::getUrl('view', ['record' => $record->zaak])),
                TextColumn::make('zaak.organisation.name')
                    ->label(__('resources/advice_thread.columns.organisation.label'))
                    ->sortable(),
                TextColumn::make('zaak.zaaktype.municipality.name')
                    ->label(__('resources/advice_thread.columns.municipality.label'))
                    ->sortable()
                    ->visible(fn () => $this->isAdvisor()),
                TextColumn::make('advisory.name')
                    ->label(__('resources/advice_thread.columns.advisory.label'))
                    ->sortable()
                    ->visible(fn () => $this->isMunicipality()),
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
                TextColumn::make('unread_messages_count')
                    ->label(__('resources/advice_thread.columns.unread_messages_count.label'))
                    ->counts('unreadMessages')
                    ->badge()
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (AdviceThread $record) => AdviceThreadResource::getUrl('view', ['record' => $record, 'zaak' => $record->zaak])),
            ])
            ->recordUrl(fn (AdviceThread $record) => AdviceThreadResource::getUrl('view', ['record' => $record, 'zaak' => $record->zaak]))
            ->defaultSort('unread_messages_count', 'desc')
            ->filters([
                AdviceStatusFilter::make(),
                UnreadMessagesFilter::make(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false);
    }
}
