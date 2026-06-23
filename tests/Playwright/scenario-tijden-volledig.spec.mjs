import { test, expect } from '@playwright/test';
import { skipAlsOpenZaakOffline } from './helpers/openzaak-check.mjs';
import { leesPdfContent, vindSectie } from './helpers/pdf-content.mjs';
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
 * Scenario: organisator vult Opbouw + Publiek + Afbouw in op de
 * Tijden-stap. De PDF/SubmissionReport hoort dan een 3-rijen-tabel
 * (Activiteit / Start / Eind) te tonen — niet zes losse rijen, niet
 * alleen Publiek.
 *
 * Regression-coverage op punt 5 (Tijden-tabel in PDF + Samenvatting).
 */
test('Tijden-stap met opbouw + publiek + afbouw → 3-rijen-tijdentabel in PDF', async ({ page }) => {
    test.setTimeout(180_000);
    skipAlsOpenZaakOffline(test);

    await verseStart(page);

    await stap1Contactgegevens(page);
    await stap2HetEvenement(page, {
        naam: 'Volledige Tijden Test',
        omschrijving: 'Test met opbouw + publiek + afbouw.',
        soort: 'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
    });
    await stap3LocatieGebouw(page, { naamLocatie: 'Buurthuis' });

    // Volledige tijden invullen — alle 6 datetime-velden.
    await stap4Tijden(page, {
        publiekStart: '2026-09-12T14:00',
        publiekEind: '2026-09-12T22:00',
        metOpbouw: true,
        opbouwStart: '2026-09-12T08:00',
        opbouwEind: '2026-09-12T13:00',
        metAfbouw: true,
        afbouwStart: '2026-09-12T22:00',
        afbouwEind: '2026-09-13T02:00',
    });

    await stap5Vooraankondiging(page, 'evenement');
    await stap6ScanMelding(page);
    await klikDoorTotIndienen(page);
    const zaakIdentifier = await indienen(page);

    const pdf = leesPdfContent(zaakIdentifier);

    const tijden = vindSectie(pdf, 'tijd');
    expect(tijden, 'sectie tijden gevonden').not.toBeNull();

    // Vind het entry met de tijden-tabel (table-shape, niet plain value).
    const tabelEntry = (tijden?.entries ?? []).find((e) => e.table);
    expect(tabelEntry, 'tijden-tabel-entry zichtbaar').not.toBeUndefined();

    const rows = tabelEntry?.table?.rows ?? [];
    const activiteiten = rows.map((r) => r[0]);
    expect(activiteiten, 'drie rij-labels: Opbouw / Publiek / Afbouw').toEqual(
        expect.arrayContaining(['Opbouw', 'Publiek', 'Afbouw']),
    );

    // Eén platte string van alle cell-values voor jaartal-check.
    const allCells = rows.flat().join(' ');
    expect(allCells).toMatch(/12 september 2026/);
    expect(allCells).toMatch(/13 september 2026/); // afbouw loopt naar volgende dag
});
