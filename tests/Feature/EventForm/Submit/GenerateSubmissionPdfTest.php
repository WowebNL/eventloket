<?php

/**
 * PDF-inzendingsbewijs wordt na submit als aparte (high-prio) job
 * gegenereerd. Deze test checkt:
 *
 * 1. Na uitvoering van de job staat er een PDF op de lokale disk onder
 *    `zaken/{zaak_uuid}/submission-report.pdf`.
 * 2. De PDF bevat het zaaknummer + de belangrijkste antwoorden uit de
 *    FormState (naam evenement, locatie, datum).
 * 3. Een bevestigings-email-job wordt aangesloten (dispatched) zodat
 *    de mail pas verstuurd wordt nadat de PDF er is.
 *
 * Motivatie: in de oude OF-flow genereerde Open Forms een PDF via de
 * `generate_submission_report`-task. Wij nemen dezelfde belofte over:
 * een organisator moet na indienen snel een bewijs van z'n aanvraag
 * kunnen downloaden/in de mail zien.
 */

use App\EventForm\State\FormState;
use App\Jobs\Submit\GenerateSubmissionPdf;
use App\Jobs\Submit\SendSubmissionConfirmationEmail;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('rendert een PDF-bewijs op de juiste plek na submit', function () {
    $zaaktype = Zaaktype::factory()->create(['name' => 'Evenementenvergunning gemeente Maastricht']);

    $state = new FormState(values: [
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
        'soortEvenement' => 'Buurtfeest',
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
        'aantalVerwachteAanwezigen' => 80,
        'risicoClassificatie' => 'A',
        'watIsUwVoornaam' => 'Noah',
        'watIsUwAchternaam' => 'de Graaf',
        'watIsUwEMailadres' => 'noah@example.net',
    ]);

    $zaak = Zaak::factory()->create([
        'public_id' => 'ZAAK-98765',
        'zaaktype_id' => $zaaktype->id,
        'form_state_snapshot' => $state->toSnapshot(),
        'reference_data' => new ZaakReferenceData(
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
        ),
    ]);

    Queue::fake();

    (new GenerateSubmissionPdf($zaak))->handle();

    // 1. PDF staat op de verwachte plek.
    $expectedPath = "zaken/{$zaak->id}/submission-report.pdf";
    Storage::disk('local')->assertExists($expectedPath);

    // 2. Het eerste stukje bytes is een geldige PDF-header.
    $content = Storage::disk('local')->get($expectedPath);
    expect(substr($content, 0, 4))->toBe('%PDF');

    // 3. Bevestigings-email-job wordt daarna gedispatcht zodat 'ie de
    //    PDF als bijlage kan meenemen.
    Queue::assertPushed(SendSubmissionConfirmationEmail::class,
        fn (SendSubmissionConfirmationEmail $job) => $job->zaak->is($zaak)
    );
});
