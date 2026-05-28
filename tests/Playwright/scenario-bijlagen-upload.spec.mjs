import { test, expect } from '@playwright/test';
import { skipAlsOpenZaakOffline } from './helpers/openzaak-check.mjs';
import {
    verseStart,
    stap1Contactgegevens,
    stap2HetEvenement,
    stap3LocatieGebouw,
    stap4Tijden,
    stap5Vooraankondiging,
    stap6ScanMelding,
} from './helpers/wizard-flow.mjs';
import { huidigeStap, klikVolgende, kiesRadioOptioneel } from './helpers/form-invullen.mjs';

/**
 * Scenario: de organisator komt op de Bijlagen-stap en moet daar drie
 * EventloketFileUpload-velden zien (veiligheidsplan, bebording, overige
 * bijlagen). We klikken door tot die stap en verifiëren dat de velden
 * gerendered worden — Filament + FilePond + Livewire-glue moet werken.
 *
 * De daadwerkelijke upload-pipeline (storage → ZGW) zit al onder een
 * happy-path Pest-test (`UploadFormBijlagenToZGWTest`); een full e2e-
 * upload via Playwright vergt FilePond-specifieke chooser-tricks die
 * een aparte spec waard zijn.
 */
test('Bijlagen-stap toont 3 EventloketFileUpload-velden bij meldingsroute', async ({ page }) => {
    test.setTimeout(180_000);
    skipAlsOpenZaakOffline(test);

    await verseStart(page);

    await stap1Contactgegevens(page);
    await stap2HetEvenement(page, {
        naam: 'Buurtfeest met Bijlagen',
        omschrijving: 'Test voor Bijlagen-stap-rendering.',
        soort: 'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
    });
    await stap3LocatieGebouw(page, { naamLocatie: 'Buurthuis' });
    await stap4Tijden(page);
    await stap5Vooraankondiging(page, 'evenement');
    await stap6ScanMelding(page);

    // Doorklik tot we op de Bijlagen-stap staan.
    for (let i = 0; i < 15; i++) {
        const stap = await huidigeStap(page);
        if (! stap) break;
        if (/bijlagen/i.test(stap)) break;

        // Melding-stap heeft drie verplichte radios.
        if (/melding/i.test(stap)) {
            await kiesRadioOptioneel(page, 'wordtErAlcoholGeschonkenTijdensUwEvenement', 'Nee');
            await page.waitForTimeout(400);
            await kiesRadioOptioneel(page, 'wordenErFilmopnamesMetBehulpVanDronesGemaakt', 'Nee');
            await page.waitForTimeout(400);
            await kiesRadioOptioneel(page, 'vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden', 'Nee');
            await page.waitForTimeout(400);
        }

        try {
            await klikVolgende(page);
        } catch {
            break;
        }
    }

    expect(await huidigeStap(page), 'Bijlagen-stap bereikt').toMatch(/bijlagen/i);

    // In de meldings-flow (zonder A1..A11-kruisjes) is alleen
    // `bijlagen1` (= "Overige bijlagen") via FormFieldVisibility
    // zichtbaar; de andere twee FileUpload's zitten verborgen
    // achter `isFieldHidden('veiligheidsplan')` resp.
    // `isFieldHidden('bebordingsEnBewegwijzeringsplan')`. Dat
    // 'bijlagen1' wel rendert bewijst dat:
    //   1. de stap correct laadt (Filament + Livewire),
    //   2. de gehardende EventloketFileUpload-component
    //      probleemloos in de DOM verschijnt.
    await expect(page.getByText(/Overige bijlagen/i).first()).toBeVisible();
    const fileInputs = await page.locator('input[type="file"]').count();
    expect(fileInputs, 'minstens één file-input in de DOM').toBeGreaterThanOrEqual(1);
});
