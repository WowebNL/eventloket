<?php

namespace App\Filament\Shared\Resources\Zaken\Widgets;

use App\Filament\Shared\Resources\Activities\Schemas\ActivityInfolist;
use App\Filament\Shared\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Message;
use App\Models\Thread;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Zaak;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogWidget extends TableWidget
{
    public Zaak $record;

    public function table(Table $table): Table
    {
        return ActivitiesTable::configure($table)
            ->query(function (): Builder {
                // Get all thread IDs for this zaak using query builder to avoid model instantiation issues
                $adviceThreadIds = Thread::query()
                    ->where('zaak_id', $this->record->id)
                    ->where('type', 'advice')
                    ->toBase()
                    ->pluck('id')
                    ->toArray();

                $organiserThreadIds = Thread::query()
                    ->where('zaak_id', $this->record->id)
                    ->where('type', 'organiser')
                    ->toBase()
                    ->pluck('id')
                    ->toArray();

                $threadIds = array_merge($adviceThreadIds, $organiserThreadIds);

                // Get all message IDs for threads of this zaak
                $messageIds = [];
                if (! empty($threadIds)) {
                    $messageIds = Message::query()
                        ->whereIn('thread_id', $threadIds)
                        ->toBase()
                        ->pluck('id')
                        ->toArray();
                }

                return Activity::query()
                    ->where(function (Builder $query) use ($threadIds, $messageIds) {
                        $query
                            // Activities whose subject IS the Zaak
                            ->where(function (Builder $q) {
                                $q->where('subject_type', Zaak::class)
                                    ->where('subject_id', $this->record->id);
                            })

                            // OR activities whose subject is a thread with this zaak_id
                            ->orWhere(function (Builder $q) use ($threadIds) {
                                $q->whereIn('subject_type', [AdviceThread::class, OrganiserThread::class])
                                    ->whereIn('subject_id', $threadIds);
                            })

                            // OR activities whose subject is a message belonging to a thread with this zaak_id
                            ->orWhere(function (Builder $q) use ($messageIds) {
                                $q->where('subject_type', Message::class)
                                    ->whereIn('subject_id', $messageIds);
                            });
                    });
            })
            ->recordActions([
                ViewAction::make()
                    ->schema(fn ($infolist) => ActivityInfolist::configure($infolist)),
            ]);
    }
}
