<?php

namespace App\Filament\Municipality\Widgets;

use App\Enums\ThreadType;
use App\Filament\Municipality\Resources\Zaken\ZaakResource;
use App\Filament\Shared\Resources\Threads\Actions\RequestAdviceAction;
use App\Filament\Shared\Resources\Threads\Filters\UnreadMessagesFilter;
use App\Filament\Shared\Resources\Threads\Tables\Components\LatestMessageColumn;
use App\Filament\Shared\Resources\Threads\Tables\Components\UnreadMessagesColumn;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\OrganiserThreads\OrganiserThreadResource;
use App\Models\Thread;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ThreadInboxWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('resources/thread.widgets.inbox.heading');
    }

    protected function threadResourceClass(Thread $thread): string
    {
        return match (get_class($thread)) {
            AdviceThread::class => AdviceThreadResource::class,
            OrganiserThread::class => OrganiserThreadResource::class,
            default => throw new \Exception('Unknown thread type'),
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('resources/thread.label'))
            ->pluralModelLabel(__('resources/thread.plural_label'))
            /** @phpstan-ignore-next-line */
            ->query(fn (): Builder => Thread::query()->whereHas('zaak.zaaktype', fn (Builder $query) => $query->where('municipality_id', Filament::getTenant()->id)))
            ->columns([
                TextColumn::make('zaak.reference_data.naam_evenement')
                    ->label(__('resources/advice_thread.columns.event.label'))
                    ->description(fn (Thread $record) => $record->zaak->public_id)
                    ->icon('heroicon-s-eye')
                    ->sortable()
                    ->url(fn (Thread $record) => ZaakResource::getUrl('view', ['record' => $record->zaak])),
                TextColumn::make('type')
                    ->label(__('resources/thread.columns.type.label'))
                    ->description(fn (Thread $record) => get_class($record) === AdviceThread::class ? $record->advisory->name : $record->zaak->organisation->name)
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('resources/advice_thread.columns.title.label'))
                    ->searchable(),
                UnreadMessagesColumn::make(),
                LatestMessageColumn::make(),
            ])
            ->recordActions([
                RequestAdviceAction::make(),
                ViewAction::make()
                    ->url(fn (Thread $record) => $this->threadResourceClass($record)::getUrl('view', ['record' => $record, 'zaak' => $record->zaak])),
            ])
            ->recordUrl(fn (Thread $record) => $this->threadResourceClass($record)::getUrl('view', ['record' => $record, 'zaak' => $record->zaak]))
            ->defaultSort('unread_messages_count', 'desc')
            ->filters([
                UnreadMessagesFilter::make(),
                SelectFilter::make('type')
                    ->options(ThreadType::class),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false);
    }
}
