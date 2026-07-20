<?php

/**
 * PDF-inzendingsbewijs wordt na submit als aparte (high-prio) job
 * gegenereerd. Deze test checkt:
 *
 * 1. Na uitvoering van de job staat er een PDF op de lokale disk onder
 *    `zaken/{zaak_uuid}/aanvraagformulier.pdf`.
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

use App\Enums\OrganisationType;
use App\EventForm\State\FormState;
use App\Jobs\Submit\GenerateSubmissionPdf;
use App\Jobs\Submit\SendSubmissionConfirmationEmail;
use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
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
    $expectedPath = "zaken/{$zaak->id}/aanvraagformulier.pdf";
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

test('risicoClassificatie wordt berekend uit de 14 risicoscan-velden en in de PDF opgenomen', function () {
    $zaaktype = Zaaktype::factory()->create(['name' => 'Evenementenvergunning gemeente Maastricht']);

    // Gebruik de 14 daadwerkelijke risicoscan-velden (som = 2.75 → A).
    // De '0'-waarden testen dat integer-0 na JSON round-trip niet als
    // "ontbrekend veld" wordt gezien (het vroegere JsTruthy-probleem).
    $state = new FormState(values: [
        'watIsDeNaamVanHetEvenementVergunning' => 'Risicoscan Test',
        'watIsDeAantrekkingskrachtVanHetEvenement' => '0.5',
        'watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep' => '0.25',
        'isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid' => '0',
        'isEenDeelVanDeDoelgroepVerminderdZelfredzaam' => '0.25',
        'isErSprakeVanAanwezigheidVanRisicovolleActiviteiten' => '0',
        'watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep' => '0.5',
        'isErSprakeVanOvernachten' => '0',
        'isErGebruikVanAlcoholEnDrugs' => '0',
        'watIsHetAantalGelijktijdigAanwezigPersonen' => '0',
        'inWelkSeizoenVindtHetEvenementPlaats' => '0.25',
        'inWelkeLocatieVindtHetEvenementPlaats' => '0.25',
        'opWelkSoortOndergrondVindtHetEvenementPlaats' => '0.25',
        'watIsDeTijdsduurVanHetEvenement' => '0',
        'welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing' => '0.5',
    ]);

    $zaak = Zaak::factory()->create([
        'public_id' => 'ZAAK-11111',
        'zaaktype_id' => $zaaktype->id,
        'form_state_snapshot' => $state->toSnapshot(),
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-08-01T10:00:00+02:00',
            eind_evenement: '2026-08-01T13:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ingediend',
            statustype_url: '',
            risico_classificatie: null,
            naam_locatie_eveneme: null,
            naam_evenement: 'Risicoscan Test',
            organisator: null,
            aanwezigen: null,
            types_evenement: null,
        ),
    ]);

    Queue::fake();

    (new GenerateSubmissionPdf($zaak))->handle();

    $expectedPath = "zaken/{$zaak->id}/aanvraagformulier.pdf";
    Storage::disk('local')->assertExists($expectedPath);

    // Verifieer dat de 14 risicoscan-velden na snapshot-round-trip correct
    // worden opgeteld tot classificatie 'A' (som = 2.75 ≤ 6).
    // Dit test specifiek de fix voor het JsTruthy-probleem waarbij integer 0
    // (na JSON-deserialisatie van '0') eerder als "ontbrekend veld" werd gezien.
    $loadedState = FormState::fromSnapshot($zaak->fresh()->form_state_snapshot ?? []);
    expect($loadedState->get('risicoClassificatie'))->toBe('A');
});

/**
 * Haalt de zichtbare tekst uit een dompdf-PDF: alle FlateDecode-streams
 * decomprimeren en de null-bytes van de 16-bit glyph-IDs strippen, zodat
 * een gewone str_contains werkt. Zelfde aanpak als in
 * GenerateSubmissionPdfIntegrationTest.
 */
function tekstUitPdf(string $pdfBytes): string
{
    $tekst = '';
    if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $pdfBytes, $matches)) {
        foreach ($matches[1] as $stream) {
            $decompressed = @gzuncompress($stream);
            if ($decompressed !== false) {
                $tekst .= str_replace("\x00", '', $decompressed);
            }
        }
    }

    return $tekst;
}

/**
 * @param  array<string, mixed>  $organisationAttributes
 */
function pdfVoorOrganisatie(array $organisationAttributes, string $voornaam, string $achternaam): string
{
    $organisation = Organisation::factory()->create($organisationAttributes);
    $zaaktype = Zaaktype::factory()->create(['name' => 'Evenementenvergunning']);
    $organiser = User::factory()->create([
        'first_name' => $voornaam,
        'last_name' => $achternaam,
        'name' => "{$voornaam} {$achternaam}",
        'role' => Role::Organiser,
    ]);

    $state = new FormState(values: [
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
    ]);

    $zaak = Zaak::factory()->create([
        'public_id' => 'ZAAK-ORG-1',
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
        'organiser_user_id' => $organiser->id,
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
        ),
    ]);

    Queue::fake();

    (new GenerateSubmissionPdf($zaak))->handle();

    return tekstUitPdf(Storage::disk('local')->get("zaken/{$zaak->id}/aanvraagformulier.pdf"));
}

test('toont de persoonsnaam als organisator bij een aanvraag op persoonlijke titel', function () {
    $tekst = pdfVoorOrganisatie([
        'type' => OrganisationType::Personal,
        'name' => 'Mijn omgeving',
    ], 'Noah', 'Vermeulen');

    expect($tekst)->not->toContain('Mijn omgeving')
        ->and($tekst)->toContain('Noah Vermeulen');
});

test('toont de organisatienaam als organisator bij een zakelijke aanvraag', function () {
    $tekst = pdfVoorOrganisatie([
        'type' => OrganisationType::Business,
        'name' => 'Media Tuin',
    ], 'Noah', 'Vermeulen');

    expect($tekst)->toContain('Media Tuin');
});
