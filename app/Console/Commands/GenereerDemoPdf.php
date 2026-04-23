<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventForm\State\FormState;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

/**
 * Dev-command dat een voorbeeld-inzendingsbewijs PDF genereert zonder
 * DB-afhankelijkheid — handig om het visuele resultaat te bekijken.
 * De PDF wordt naar `storage/app/demo-submission-report.pdf` geschreven.
 */
class GenereerDemoPdf extends Command
{
    protected $signature = 'eventform:genereer-demo-pdf';

    protected $description = 'Genereer een demo PDF-inzendingsbewijs (in-memory, geen DB nodig)';

    public function handle(): int
    {
        $state = new FormState(values: [
            'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
            'soortEvenement' => 'Buurtfeest',
            'EvenementStart' => '2026-06-14T14:00',
            'EvenementEind' => '2026-06-14T18:00',
            'OpbouwStart' => '2026-06-14T12:00',
            'OpbouwEind' => '2026-06-14T13:30',
            'AfbouwStart' => '2026-06-14T18:00',
            'AfbouwEind' => '2026-06-14T19:30',
            'aantalVerwachteAanwezigen' => 80,
            'risicoClassificatie' => 'A',
            'watIsUwVoornaam' => 'Noah',
            'watIsUwAchternaam' => 'de Graaf',
            'watIsUwEMailadres' => 'noah.degraaf@example.net',
            'watIsUwTelefoonnummer' => '06-12345678',
            'watIsDeNaamVanUwOrganisatie' => 'Media Tuin',
            'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
            'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning' => 'Een kleinschalig buurtfeest op een middag met 80 bewoners.',
        ]);

        $refData = new ZaakReferenceData(
            start_evenement: '2026-06-14T14:00:00+02:00',
            eind_evenement: '2026-06-14T18:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ingediend',
            statustype_url: '',
            risico_classificatie: 'A',
            naam_locatie_eveneme: 'Buurtcentrum De Hoek',
            naam_evenement: 'Buurtfeest Testlaan',
            organisator: 'Media Tuin',
            aanwezigen: '80',
            types_evenement: 'Buurtfeest',
            start_opbouw: '2026-06-14T12:00:00+02:00',
            eind_opbouw: '2026-06-14T13:30:00+02:00',
            start_afbouw: '2026-06-14T18:00:00+02:00',
            eind_afbouw: '2026-06-14T19:30:00+02:00',
        );

        $zaak = new Zaak([
            'public_id' => 'DEMO-PDF-'.substr(uniqid(), -6),
            'zgw_zaak_url' => 'https://example.com/demo/'.uniqid(),
        ]);
        $zaak->id = (string) \Illuminate\Support\Str::uuid();
        $zaak->reference_data = $refData;
        $zaak->form_state_snapshot = $state->toSnapshot();
        $zaak->created_at = now();
        $zaak->setRelation('zaaktype', new Zaaktype(['name' => 'Evenementenvergunning gemeente Maastricht']));
        $zaak->setRelation('organisation', new \App\Models\Organisation(['name' => 'Media Tuin']));

        $rows = [
            'Naam evenement' => $refData->naam_evenement,
            'Type evenement' => $refData->types_evenement,
            'Omschrijving' => $state->get('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning'),
            'Locatie' => $refData->naam_locatie_evenement,
            'Start evenement' => $this->human($refData->start_evenement),
            'Eind evenement' => $this->human($refData->eind_evenement),
            'Start opbouw' => $this->human($refData->start_opbouw),
            'Eind opbouw' => $this->human($refData->eind_opbouw),
            'Start afbouw' => $this->human($refData->start_afbouw),
            'Eind afbouw' => $this->human($refData->eind_afbouw),
            'Verwacht aantal aanwezigen' => $refData->aanwezigen,
            'Risicoclassificatie' => $refData->risico_classificatie,
            'Organisator' => $refData->organisator,
            'Naam contactpersoon' => trim(((string) $state->get('watIsUwVoornaam')).' '.((string) $state->get('watIsUwAchternaam'))),
            'E-mailadres' => $state->get('watIsUwEMailadres'),
            'Telefoonnummer' => $state->get('watIsUwTelefoonnummer'),
            'KvK-nummer' => $state->get('watIsHetKamerVanKoophandelNummerVanUwOrganisatie'),
            'Organisatienaam' => $state->get('watIsDeNaamVanUwOrganisatie'),
        ];

        $pdf = Pdf::loadView('pdf.submission-report', [
            'zaak' => $zaak,
            'state' => $state,
            'rows' => $rows,
        ])->setPaper('a4');

        $target = storage_path('app/demo-submission-report.pdf');
        file_put_contents($target, $pdf->output());

        $this->info('PDF geschreven naar: '.$target);
        $this->info('Host-pad: storage/app/demo-submission-report.pdf');

        return self::SUCCESS;
    }

    private function human(?string $iso): ?string
    {
        if (! $iso) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($iso, 'Europe/Amsterdam')->translatedFormat('j F Y · H:i');
        } catch (\Throwable) {
            return $iso;
        }
    }
}
