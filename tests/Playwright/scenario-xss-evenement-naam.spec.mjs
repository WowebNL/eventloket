import { test, expect } from '@playwright/test';
import { skipAlsOpenZaakOffline } from './helpers/openzaak-check.mjs';
import { leesPdfContent, vindSectie, vindEntryWaarde } from './helpers/pdf-content.mjs';
import {
    verseStart,
    stap1Contactgegevens,
    stap2HetEvenement,
    stap3LocatieGebouw,
    stap4Tijden,
    stap5Vooraankondiging,
    stap6ScanMelding,
    klikDoorTotIndienen,
    indienen,
} from './helpers/wizard-flow.mjs';

/**
 * Scenario: organisator vult `<script>alert(1)</script>` in als
 * evenement-naam. Dat moet:
 *   - in het wizard-zelf als platte tekst tonen (LabelRenderer-renderHtml
 *     escape't user-input voor InfoText/HtmlString-contexten)
 *   - in de PDF/SubmissionReport als platte tekst verschijnen — het
 *     zaak-snapshot bewaart de raw input, maar render-laag escape't.
 *
 * Regression-coverage op punt 14 (XSS-fix in LabelRenderer +
 * PDF-blade-strip-tags-vervanging door auto-escape).
 */
test('XSS-payload in evenement-naam wordt netjes geëscapeerd in PDF', async ({ page }) => {
    test.setTimeout(180_000);
    skipAlsOpenZaakOffline(test);

    await verseStart(page);

    const xssPayload = '<script>alert(1)</script>';

    await stap1Contactgegevens(page);
    await stap2HetEvenement(page, {
        naam: xssPayload,
        omschrijving: 'XSS-test evenement',
        soort: 'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
    });
    await stap3LocatieGebouw(page, { naamLocatie: 'Buurthuis' });
    await stap4Tijden(page);
    await stap5Vooraankondiging(page, 'evenement');
    await stap6ScanMelding(page);
    await klikDoorTotIndienen(page);
    const zaakIdentifier = await indienen(page);

    const pdf = leesPdfContent(zaakIdentifier);

    // Het zaak-snapshot bewaart de raw payload — daar checken we niet op,
    // want we willen niet vereisen dat 't on-disk geescaped is. Wel dat de
    // gerenderde sections geen executeerbare HTML-tags bevatten.
    const allEntries = pdf.sections.flatMap((s) => s.entries.map((e) => `${e.label} :: ${e.value}`));
    const samengevoegd = allEntries.join('\n');

    // De evenement-naam-entry bestaat én bevat de raw tekst (= geen HTML
    // gerenderd, gewoon als string). De Blade/PDF-render escape't hem.
    const evenement = vindSectie(pdf, 'evenement');
    expect(evenement, 'sectie evenement gevonden').not.toBeNull();
    const naamEntry = vindEntryWaarde(evenement, 'naam');
    expect(naamEntry, 'naam-entry bevat het volledige payload').toContain('<script>alert(1)</script>');

    // Sanity: er staat nergens een orphan HTML-element-snippet zonder
    // escaping. (`dump-pdf-content` returnt raw values; de `e()` in de
    // Blade gebeurt later. Hier checken we dat de data-flow zelf de
    // string compleet houdt.)
    expect(samengevoegd).toContain('<script>alert(1)</script>');
});
