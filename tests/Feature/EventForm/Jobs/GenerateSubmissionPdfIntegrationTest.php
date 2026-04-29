<?php

declare(strict_types=1);

/**
 * End-to-end-controle van de PDF-generatie: vanaf een Zaak met een
 * realistische form_state_snapshot tot het binaire PDF-bestand op disk.
 *
 * De opdrachtgever rapporteerde dat het oude inzendingsbewijs (18
 * platte velden) bij lange na niet alles bevatte wat er op het
 * formulier ingevuld kan worden. SubmissionReport-unit-tests bewijzen
 * de logica per stap; deze test bewijst dat de hele keten samen
 * (GenerateSubmissionPdf → SubmissionReport → blade-template → dompdf)
 * een PDF oplevert waar de per-stap-secties én concrete waarden
 * daadwerkelijk in terechtkomen.
 */

use App\Jobs\Submit\GenerateSubmissionPdf;
use App\Jobs\Submit\SendSubmissionConfirmationEmail;
use App\Jobs\Submit\UploadSubmissionPdfToZGW;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Notification::fake();
    Storage::fake('local');
});

test('PDF bevat per-stap secties + concrete ingevulde waarden uit het formulier', function () {
    $municipality = Municipality::factory()->create(['name' => 'Heerlen']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Evenementenvergunning gemeente Heerlen',
    ]);
    $organisation = Organisation::factory()->create(['name' => 'Media Tuin']);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $organisation->id,
        'zaaktype_id' => $zaaktype->id,
        'public_id' => 'ZAAK-2026-0042',
        // Het werkelijke bewijs: een breed gevuld FormState-snapshot dat
        // contact, evenement-naam, locatie-keuze, tijden en wat
        // bijvragen dekt. Niet 1-op-1 alle 144 velden — wel genoeg om te
        // bewijzen dat ze terechtkomen via SubmissionReport.
        'form_state_snapshot' => [
            'values' => [
                'watIsUwVoornaam' => 'Eva',
                'watIsUwAchternaam' => 'de Vries',
                'watIsUwEMailadres' => 'eva@example.nl',
                'watIsUwTelefoonnummer' => '0612345678',
                'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
                'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning' => 'Een gezellig buurtfeest op een zondagmiddag.',
                'soortEvenement' => 'Buurt-, barbecue of straatfeest',
                'EvenementStart' => '2026-06-14T14:00',
                'EvenementEind' => '2026-06-14T18:00',
                'aantalVerwachteAanwezigen' => '80',
                'wordtErAlcoholGeschonkenTijdensUwEvenement' => 'Nee',
            ],
            'fieldHidden' => [],
            'stepApplicable' => [],
            'system' => [],
        ],
    ]);

    (new GenerateSubmissionPdf($zaak))->handle();

    $pdfPath = sprintf('zaken/%s/submission-report.pdf', $zaak->id);
    expect(Storage::disk('local')->exists($pdfPath))
        ->toBeTrue('verwacht een PDF op '.$pdfPath);

    $pdfBytes = Storage::disk('local')->get($pdfPath);

    // 1. Het is een geldig PDF-bestand (begin-byte controle).
    expect(str_starts_with($pdfBytes, '%PDF-'))->toBeTrue();
    expect(strlen($pdfBytes))->toBeGreaterThan(2_000);

    // 2. Pak alle FlateDecode-streams uit de PDF en decomprimeer ze; daar
    //    zitten de Tj/TJ-text-operators in waar dompdf de zichtbare
    //    tekst in encodeert. Subsetted fonts gebruiken 16-bit glyph-IDs
    //    (UTF-16BE), dus tussen elk leesbaar karakter zit een null-byte
    //    in de stream — die strippen we hier uit zodat een gewone
    //    str_contains-match op "Contactgegevens" ook werkt.
    $tekstUitStreams = '';
    if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $pdfBytes, $matches)) {
        foreach ($matches[1] as $stream) {
            $decompressed = @gzuncompress($stream);
            if ($decompressed !== false) {
                $tekstUitStreams .= str_replace("\x00", '', $decompressed);
            }
        }
    }

    // 3. Per-stap-secties uit SubmissionReport komen terug als zichtbare
    //    tekst in de gedecomprimeerde streams.
    foreach (['Contactgegevens', 'Het evenement', 'Tijden'] as $sectie) {
        expect(str_contains($tekstUitStreams, $sectie))
            ->toBeTrue("verwacht sectie '{$sectie}' in de PDF");
    }

    // 4. Concrete ingevulde waarden komen ook terug — dat bewijst dat
    //    de FormState-walk de waarden ophaalt en niet alleen labels
    //    rendert.
    foreach (['Eva', 'Buurtfeest Testlaan', '14 juni 2026'] as $waarde) {
        expect(str_contains($tekstUitStreams, $waarde))
            ->toBeTrue("verwacht ingevulde waarde '{$waarde}' in de PDF");
    }

    // 4. Vervolg-jobs zijn gedispatched: bevestigingsmail + ZGW-upload.
    Bus::assertDispatched(SendSubmissionConfirmationEmail::class);
    Bus::assertDispatched(UploadSubmissionPdfToZGW::class);
});
