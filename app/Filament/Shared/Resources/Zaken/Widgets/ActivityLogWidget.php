<?php

namespace App\Filament\Shared\Resources\Zaken\Widgets;

use App\Filament\Shared\Resources\Activities\Schemas\ActivityInfolist;
use App\Filament\Shared\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Message;
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
                return Activity::query()
                    ->where(function (Builder $query) {
                        $query
                            // Activities whose subject IS the Zaak
                            ->where(function (Builder $q) {
                                $q->where('subject_type', Zaak::class)
                                    ->where('subject_id', $this->record->id);
                            })

                            // OR activities whose subject is a thread with this zaak_id
                            ->orWhereHasMorph(
                                'subject',
                                [AdviceThread::class, OrganiserThread::class],
                                function (Builder $q) {
                                    $q->where('zaak_id', $this->record->id);
                                }
                            )

                            // OR activities whose subject is a message belonging to a thread with this zaak_id
                            ->orWhereHasMorph(
                                'subject',
                                [Message::class],
                                function (Builder $q) {
                                    $q->whereHas('thread', fn ($q) => $q->where('zaak_id', $this->record->id));
                                }
                            );
                    });
            })
            ->recordActions([
                ViewAction::make()
                    ->schema(fn ($infolist) => ActivityInfolist::configure($infolist)),
            ]);
    }
}
