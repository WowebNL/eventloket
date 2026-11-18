<?php

namespace App\Services\Archiving;

use App\Models\Archiving\DestructionReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the PDF of a destruction report and stores it.
 *
 * Always rendered from the stored report row, never from the list or the log
 * rows it was built out of, so a regenerated PDF is identical to the one issued
 * originally and a report whose source rows are gone can still be rebuilt.
 *
 * Both report types come through here, which is what lets the regenerate action
 * repair either one.
 */
class DestructionReportPdf
{
    public function store(DestructionReport $report): void
    {
        $path = "archief/rapporten/{$report->batch_number}.pdf";

        Storage::disk(config('archiving.report_disk'))->put(
            $path,
            Pdf::loadView('pdf.destruction-report', ['report' => $report])->output(),
        );

        $report->update(['pdf_path' => $path]);
    }
}
