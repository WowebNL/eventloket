<?php

namespace App\Filament\Municipality\Clusters\Archiving\Actions;

use App\Jobs\Archiving\GenerateDestructionReport;
use App\Models\Archiving\DestructionList;
use App\Models\Archiving\DestructionReport;
use App\Services\Archiving\DestructionReportPdf;
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
                if ($list !== null) {
                    return ! $list->report?->hasPdf()
                        && auth()->user()->can('regenerateReport', $list);
                }

                // A report with no list behind it is the generated
                // Eventloket-data one. Its rows are already marked reported, so
                // the nightly command will not produce it again and this is the
                // only way back to a readable PDF.
                return $record instanceof DestructionReport
                    && ! $record->hasPdf()
                    && auth()->user()->can('regenerate', $record);
            })
            ->requiresConfirmation()
            ->modalDescription(__('municipality/resources/destruction_report.actions.regenerate.modal_description'))
            ->action(function (Model $record) {
                $list = self::destructionList($record);

                if ($list !== null) {
                    GenerateDestructionReport::dispatch($list->id);
                } elseif ($record instanceof DestructionReport) {
                    // Rendered straight from the stored row: the report itself
                    // is intact, only its PDF is missing.
                    app(DestructionReportPdf::class)->store($record);
                } else {
                    return;
                }

                Notification::make()
                    ->title(__('municipality/resources/destruction_report.actions.regenerate.notification.title'))
                    ->success()
                    ->send();
            });
    }

    /**
     * The list to regenerate from, whichever side the action sits on, or null
     * when the report has none. A zaakdata report is rebuilt through its list,
     * which still holds the item snapshots; an Eventloket-data report has no
     * list and is re-rendered from its own stored row.
     */
    private static function destructionList(Model $record): ?DestructionList
    {
        if ($record instanceof DestructionReport) {
            return $record->destructionList;
        }

        return $record instanceof DestructionList ? $record : null;
    }
}
