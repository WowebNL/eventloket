<?php

namespace App\Filament\Municipality\Clusters\Archiving\Actions;

use App\Jobs\Archiving\GenerateDestructionReport;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Rebuilds a destruction report that is missing, or whose PDF can no longer
 * be read. Everything is rendered again from the snapshots stored when the
 * list was destroyed, so an existing report keeps its number and contents.
 *
 * Reachable from both sides of the archive: a destroyed list that never got
 * its report, and a report that lost its PDF.
 */
class RegenerateDestructionReportAction
{
    public static function make(): Action
    {
        return Action::make('regenerate_report')
            ->label(__('municipality/resources/destruction_report.actions.regenerate.label'))
            ->icon('heroicon-o-arrow-path')
            ->visible(function (Model $record): bool {
                $list = self::destructionList($record);

                // Only offered while there is no proof of destruction that can
                // actually be opened: no report, or a report without its PDF.
                return $list !== null
                    && ! $list->report?->hasPdf()
                    && auth()->user()->can('regenerateReport', $list);
            })
            ->requiresConfirmation()
            ->modalDescription(__('municipality/resources/destruction_report.actions.regenerate.modal_description'))
            ->action(function (Model $record) {
                $list = self::destructionList($record);

                if ($list === null) {
                    return;
                }

                GenerateDestructionReport::dispatch($list->id);

                Notification::make()
                    ->title(__('municipality/resources/destruction_report.actions.regenerate.notification.title'))
                    ->success()
                    ->send();
            });
    }

    /**
     * The list to regenerate from, whichever side the action sits on. A report
     * whose list is gone has nothing left to rebuild from.
     */
    private static function destructionList(Model $record): ?DestructionList
    {
        if ($record instanceof DestructionReport) {
            return $record->destructionList;
        }

        return $record instanceof DestructionList ? $record : null;
    }
}
