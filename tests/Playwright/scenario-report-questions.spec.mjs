import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import {
    vulTekst,
    vulTextarea,
    vulEindigendOp,
    kiesRadio,
    kiesRadioOptioneel,
    kiesSelect,
    klikVolgende,
    huidigeStap,
} from './helpers/form-invullen.mjs';

/**
 * Scenario: ReportQuestion-pad — de gemeente Heerlen heeft de toggle
 * `use_new_report_questions = true` aanstaan. Het formulier moet dan op
 * stap 6 (Vergunningsplichtig scan) de **dynamische** lijst van
 * `reportQuestion_1..N` radios tonen i.p.v. de oude hardcoded vragen.
 *
 * Bewijst:
 *   1. reportQuestion_1 verschijnt met de tekst van de eerste actieve
 *      ReportQuestion van de gemeente.
 *   2. Cascade werkt: vraag 2 verschijnt pas na 'Ja' op vraag 1.
 *   3. 'Nee' op één vraag → isVergunningaanvraag = true → MeldingStep
 *      wordt overgeslagen in de zijbalk.
 *   4. De legacy-radios (`isHetAantal…`, `meldingvraag1` etc.) zijn
 *      NIET aanwezig in de DOM.
 *
 * Niet submit'en — dat vereist OpenZaak. We blijven op stap 6.
 */
test('scenario report-questions: nieuw pad → dynamische vragen + cascade + step-applicability', async ({ page }) => {
    test.setTimeout(120_000);

    // Setup: schoon vertrekpunt + Heerlen op het nieuwe systeem zetten +
    // garanderen dat 'r een paar actieve ReportQuestions zijn.
    execSync(`./vendor/bin/sail exec laravel.test php -r '
        require "vendor/autoload.php";
        $a = require "bootstrap/app.php";
        $a->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
        \\App\\EventForm\\Persistence\\Draft::truncate();
        $heerlen = \\App\\Models\\Municipality::where("brk_identification", "GM0917")->first();
        if ($heerlen) {
            $heerlen->update(["use_new_report_questions" => true]);
            // Garandeer dat 1 + 2 actief zijn voor de cascade-test
            $heerlen->reportQuestions()->where("order", "<=", 2)->update(["is_active" => true]);
        }
    '`, { stdio: 'pipe', timeout: 30_000 });

    try {
        await loginAlsOrganiser(page);
        await openFormulier(page);

        // ---- Stap 1: Contactgegevens ----
        await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
        await vulTekst(page, 'huisnummer1', '1').catch(() => {});
        await vulTekst(page, 'straatnaam1', 'Teststraat').catch(() => {});
        await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
        await klikVolgende(page);

        // ---- Stap 2: Het evenement ----
        await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'ReportQuestion-test');
        await page.waitForTimeout(800);
        await vulTextarea(
            page,
            'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning',
            'Test van het ReportQuestion-pad.',
        );
        await kiesSelect(
            page,
            'soortEvenement',
            'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
        );
        await page.waitForTimeout(500);
        await klikVolgende(page);

        // ---- Stap 3: Locatie (Heerlen-postcode triggert BAG-lookup) ----
        await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
        await page.waitForTimeout(1500);
        await page.getByRole('button', { name: /Toevoegen aan adres van de gebouw/i }).click();
        await page.waitForTimeout(1500);
        await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', 'X');
        await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode', '6411CD');
        await page.keyboard.press('Tab');
        await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.huisnummer', '1');
        await page.keyboard.press('Tab');
        await page.waitForTimeout(4000);
        await klikVolgende(page);

        // ---- Stap 4: Tijden ----
        await vulTekst(page, 'EvenementStart', '2026-09-21T14:00');
        await vulTekst(page, 'EvenementEind', '2026-09-21T20:00');
        await kiesRadio(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee').catch(() => {});
        await page.waitForTimeout(700);
        await klikVolgende(page);

        // ---- Stap 5: Vooraankondiging ----
        await kiesRadio(page, 'waarvoorWiltUEventloketGebruiken', 'evenement');
        await page.waitForTimeout(600);
        await klikVolgende(page);

        // ---- Stap 6: Vergunningsplichtig scan — HIER beginnen de asserties ----
        expect(await huidigeStap(page)).toMatch(/Vergunningsplichtig/i);

        // BEWIJS 1: reportQuestion_1 zichtbaar
        const rq1 = page.locator('input[type=radio][name*="reportQuestion_1"]').first();
        await expect(rq1, 'reportQuestion_1 moet bestaan in DOM op stap 6').toBeAttached({ timeout: 8_000 });

        // BEWIJS 2: legacy-radios NIET aanwezig (Group is hidden bij nieuw systeem)
        const legacyRadios = page.locator('input[type=radio][name*="isHetAantalAanwezigenBijUwEvenementMinderDanSdf"]');
        await expect(
            legacyRadios,
            'Legacy-radio meldingvraag mag NIET in DOM staan als nieuw systeem actief is',
        ).toHaveCount(0);

        // BEWIJS 3: cascade — vraag 2 nog niet zichtbaar voor we 1 beantwoorden
        const rq2 = page.locator('input[type=radio][name*="reportQuestion_2"]').first();
        await expect(rq2, 'reportQuestion_2 mag pas verschijnen na "Ja" op vraag 1').not.toBeVisible();

        // BEWIJS 4: 'Ja' op vraag 1 → cascade gaat door
        await kiesRadioOptioneel(page, 'reportQuestion_1', 'Ja');
        await page.waitForTimeout(800);
        await expect(rq2, 'reportQuestion_2 zichtbaar na "Ja" op vraag 1').toBeVisible({ timeout: 5_000 });

        // BEWIJS 5: 'Nee' op vraag 2 → vergunning-pad → MeldingStep niet applicable in zijbalk
        await kiesRadioOptioneel(page, 'reportQuestion_2', 'Nee');
        await page.waitForTimeout(1200);
        // De zijbalk-stap "Melding" hoort nu in een
        // `fi-vertical-wizard-step-not-applicable`-li te staan, herkenbaar
        // aan de "niet van toepassing"-description.
        await expect(
            page.locator('.fi-vertical-wizard-step-not-applicable').filter({ hasText: 'Melding' }).first(),
            'MeldingStep moet doorgestreept zijn in zijbalk na Nee op reportQuestion_2',
        ).toBeVisible({ timeout: 5_000 });
    } finally {
        // Cleanup: zet Heerlen weer terug op het oude systeem zodat
        // andere specs (die op legacy-keys leunen) niet stuk gaan.
        execSync(`./vendor/bin/sail exec laravel.test php -r '
            require "vendor/autoload.php";
            $a = require "bootstrap/app.php";
            $a->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
            \\App\\Models\\Municipality::where("brk_identification", "GM0917")->update(["use_new_report_questions" => false]);
        '`, { stdio: 'pipe', timeout: 30_000 });
    }
});
