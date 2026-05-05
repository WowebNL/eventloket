<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\State\FormState;
use App\Models\Zaak;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Rendert een PDF-inzendingsbewijs met alle belangrijkste antwoorden
 * uit de aanvraag en slaat 'm op bij
 * `storage/app/zaken/{zaak_uuid}/submission-report.pdf`.
 *
 * Op de `high`-queue omdat de organisator 'm snel moet kunnen
 * downloaden + in de bevestigingsmail als bijlage krijgt.
 */
final class GenerateSubmissionPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(): void
    {
        $state = FormState::fromSnapshot($this->zaak->form_state_snapshot ?? []);

        // Bouw het overzicht via SubmissionReport: walkt elke stap, pakt
        // alle ingevulde velden + hun labels, groepeert per stap. Lege
        // stappen vallen weg zodat de PDF niet vol staat met "—".
        $sections = app(SubmissionReport::class)->build($state, EventFormSchema::stepsForReport());

        // Afgeleide variabelen voor de meta-header (FormDerivedState
        // berekent ze on-the-fly uit de state).
        $evenementInGemeente = $state->get('evenementInGemeente');
        $gemeenteNaam = is_array($evenementInGemeente) ? ($evenementInGemeente['name'] ?? null) : null;
        $risicoClassificatie = $state->get('risicoClassificatie');

        $pdf = Pdf::loadView('pdf.submission-report', [
            'zaak' => $this->zaak->loadMissing(['zaaktype', 'organisation']),
            'state' => $state,
            'sections' => $sections,
            'gemeenteNaam' => $gemeenteNaam,
            'risicoClassificatie' => $risicoClassificatie,
        ])->setPaper('a4');

        $path = sprintf('zaken/%s/submission-report.pdf', $this->zaak->id);
        Storage::disk('local')->put($path, $pdf->output());

        // Pas bevestigingsmail dispatchen nadat de PDF klaar staat, zodat
        // we 'm meteen als bijlage mee kunnen sturen.
        SendSubmissionConfirmationEmail::dispatch($this->zaak);

        // Upload de PDF nu pas naar OpenZaak — vóór deze job is er nog
        // niets om te uploaden. Losse dispatch zodat een ZGW-fout deze
        // PDF-write niet retried.
        UploadSubmissionPdfToZGW::dispatch($this->zaak);
    }
}
