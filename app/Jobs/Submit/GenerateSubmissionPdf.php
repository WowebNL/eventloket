<?php

declare(strict_types=1);

namespace App\Jobs\Submit;

use App\EventForm\State\FormState;
use App\Models\Zaak;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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
        $reference = $this->zaak->reference_data;

        $rows = [
            'Naam evenement' => $reference->naam_evenement,
            'Type evenement' => $reference->types_evenement,
            'Omschrijving' => $state->get('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning'),
            'Locatie' => $reference->naam_locatie_evenement,
            'Start evenement' => $this->humanDate($reference->start_evenement),
            'Eind evenement' => $this->humanDate($reference->eind_evenement),
            'Start opbouw' => $this->humanDate($reference->start_opbouw),
            'Eind opbouw' => $this->humanDate($reference->eind_opbouw),
            'Start afbouw' => $this->humanDate($reference->start_afbouw),
            'Eind afbouw' => $this->humanDate($reference->eind_afbouw),
            'Verwacht aantal aanwezigen' => $reference->aanwezigen,
            'Risicoclassificatie' => $reference->risico_classificatie,
            'Organisator' => $reference->organisator,
            'Naam contactpersoon' => trim(((string) $state->get('watIsUwVoornaam')).' '.((string) $state->get('watIsUwAchternaam'))),
            'E-mailadres' => $state->get('watIsUwEMailadres'),
            'Telefoonnummer' => $state->get('watIsUwTelefoonnummer'),
            'KvK-nummer' => $state->get('watIsHetKamerVanKoophandelNummerVanUwOrganisatie'),
            'Organisatienaam' => $state->get('watIsDeNaamVanUwOrganisatie'),
        ];

        $pdf = Pdf::loadView('pdf.submission-report', [
            'zaak' => $this->zaak->loadMissing(['zaaktype', 'organisation']),
            'state' => $state,
            'rows' => $rows,
        ])->setPaper('a4');

        $path = sprintf('zaken/%s/submission-report.pdf', $this->zaak->id);
        Storage::disk('local')->put($path, $pdf->output());

        // Pas bevestigingsmail dispatchen nadat de PDF klaar staat, zodat
        // we 'm meteen als bijlage mee kunnen sturen.
        SendSubmissionConfirmationEmail::dispatch($this->zaak);
    }

    private function humanDate(?string $iso): ?string
    {
        if (! $iso) {
            return null;
        }

        try {
            return Carbon::parse($iso, 'Europe/Amsterdam')
                ->translatedFormat('j F Y · H:i');
        } catch (\Throwable) {
            return $iso;
        }
    }
}
