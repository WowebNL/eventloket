<?php

namespace App\Console\Commands\Archiving;

use App\Enums\DestructionReportType;
use App\Models\Archiving\DestructionReport;
use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Municipality;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Rolls the destruction log into one "Eventloket data" report per municipality.
 *
 * Zaken are usually destroyed in batches, so a report per zaak would bury the
 * evidence in noise. One report a night per municipality stays readable while
 * still recording, per zaak, what was removed and on whose say-so.
 *
 * Idempotent: a row is only picked up while its reported_at is null, so a
 * second run the same night adds nothing.
 */
class ReportEventloketDestructions extends Command
{
    protected $signature = 'archiving:report-eventloket-destructions
                            {--date= : Report on everything destroyed up to this day (Y-m-d), defaults to yesterday}
                            {--dry-run : Show what would be reported without writing anything}';

    protected $description = 'Report the Eventloket data destroyed after zaken were removed from a zaaksysteem';

    public function handle(): int
    {
        $upTo = ($this->option('date') ? Carbon::parse((string) $this->option('date')) : now()->subDay())->endOfDay();
        $dryRun = (bool) $this->option('dry-run');

        // An upper bound rather than a single day: a row an earlier run missed
        // (a worker that died, a notification that arrived late) is picked up
        // now instead of staying unreported forever.
        $logsByMunicipality = ZaakDestructionLog::query()
            ->unreported()
            ->where('destroyed_at', '<=', $upTo)
            ->orderBy('destroyed_at')
            ->get()
            ->groupBy('municipality_id');

        if ($logsByMunicipality->isEmpty()) {
            $this->info('Nothing to report.');

            return self::SUCCESS;
        }

        foreach ($logsByMunicipality as $municipalityId => $logs) {
            $municipality = $municipalityId === null ? null : Municipality::find($municipalityId);

            if ($municipality === null) {
                // The municipality is gone, so there is nobody to file a report
                // with. The rows stay, and stay unreported, as the record.
                $this->warn("Skipped {$logs->count()} destroyed zaken without a municipality.");

                continue;
            }

            if ($dryRun) {
                $this->line("{$municipality->name}: {$logs->count()} zaken would be reported.");

                continue;
            }

            $report = $this->report($municipality, $logs);

            $this->info("{$municipality->name}: reported {$logs->count()} zaken as {$report->batch_number}.");
        }

        return self::SUCCESS;
    }

    /**
     * Write the report and mark its rows reported in one transaction, so a row
     * can never be marked reported without a report to point at.
     *
     * @param  Collection<int, ZaakDestructionLog>  $logs
     */
    private function report(Municipality $municipality, Collection $logs): DestructionReport
    {
        $report = DB::transaction(function () use ($municipality, $logs): DestructionReport {
            // Locking the municipality serialises the batch numbering against
            // a destruction list finalising at the same moment: both draw from
            // the same per-municipality sequence.
            $locked = Municipality::whereKey($municipality->id)->lockForUpdate()->firstOrFail();

            $report = DestructionReport::create([
                'municipality_id' => $locked->id,
                'destruction_list_id' => null,
                'type' => DestructionReportType::EventloketData,
                'batch_number' => DestructionReport::nextBatchNumber($locked),
                // Generated, so there is no coordinator who confirmed it.
                'coordinator_name' => null,
                'coordinator_function' => null,
                'coordinator_user_id' => null,
                'destruction_method' => config('archiving.eventloket_destruction_method'),
                'destruction_date' => now(),
                'items' => $logs->map(fn (ZaakDestructionLog $log): array => $log->toReportEntry())->values()->all(),
                'total_count' => $logs->count(),
                'deleted_count' => $logs->count(),
                'failed_count' => 0,
                'skipped_count' => 0,
            ]);

            ZaakDestructionLog::whereIn('id', $logs->pluck('id'))->update([
                'destruction_report_id' => $report->id,
                'reported_at' => now(),
            ]);

            return $report;
        });

        $this->storePdf($report);

        return $report;
    }

    /**
     * Rendered from the stored report, never from the log rows, so a
     * regenerated PDF is identical to the one issued originally.
     */
    private function storePdf(DestructionReport $report): void
    {
        $path = "archief/rapporten/{$report->batch_number}.pdf";

        Storage::disk(config('archiving.report_disk'))->put(
            $path,
            Pdf::loadView('pdf.destruction-report', ['report' => $report])->output(),
        );

        $report->update(['pdf_path' => $path]);
    }
}
