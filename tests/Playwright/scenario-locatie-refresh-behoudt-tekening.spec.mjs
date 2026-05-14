import { test, expect } from '@playwright/test';
import { leegDraftDb } from './helpers/wizard-flow.mjs';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import {
    vulTekst,
    vulTextarea,
    vulEindigendOp,
    kiesSelect,
    klikVolgende,
    huidigeStap,
} from './helpers/form-invullen.mjs';
import {
    tekenPolygonOpEersteKaart,
    aantalGetekendeShapesOpEersteKaart,
} from './helpers/map-tekenen.mjs';

/**
 * Bug-reproductie: na het tekenen van een vlak op de locatiekaart en
 * vervolgens de pagina refreshen is de tekening weg.
 *
 * Verwachting: de getekende polygon moet na een page reload terug
 * verschijnen op de map (komt uit de Draft-state in de DB).
 */
test('locatie: getekend vlak overleeft een page-refresh', async ({ page }) => {
    test.setTimeout(120_000);

    await leegDraftDb();

    await loginAlsOrganiser(page);
    await openFormulier(page);

    // ---------- Stap 1: Contactgegevens ------------------------------
    await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
    await vulTekst(page, 'huisnummer1', '1').catch(() => {});
    await vulTekst(page, 'straatnaam1', 'Marktplein').catch(() => {});
    await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
    await klikVolgende(page);

    // ---------- Stap 2: Het evenement --------------------------------
    expect(await huidigeStap(page)).toMatch(/Het evenement/i);
    await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Vlak op kaart test');
    await page.waitForTimeout(800);
    await vulTextarea(page, 'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning', 'Test omschrijving voor map-refresh-scenario.');
    await kiesSelect(page, 'soortEvenement', 'Festival');
    await page.waitForTimeout(500);
    await klikVolgende(page);

    // ---------- Stap 3: Locatie - kies "Op een (buiten)locatie" + kaart ---
    expect(await huidigeStap(page)).toMatch(/Locatie/i);

    await page.getByRole('checkbox', { name: /Buiten op één of meerdere plaatsen/i }).check();
    await page.waitForTimeout(1500);

    // Open een eerste locatie-repeater-row zodat de map verschijnt.
    const toevoegenKnop = page.getByRole('button', { name: /Toevoegen aan locatie/i }).first();
    if (await toevoegenKnop.count() > 0) {
        await toevoegenKnop.click();
        await page.waitForTimeout(1500);
    }

    await vulEindigendOp(page, 'input', '.naamVanDeLocatieKaart', 'Testterrein').catch(() => {});

    // Wacht tot leaflet-map daadwerkelijk gerenderd is.
    await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
    await page.waitForTimeout(800);

    // ---------- Teken het polygon ------------------------------------
    const tekenResult = await tekenPolygonOpEersteKaart(page);
    expect(tekenResult.ok, `polygon-tekenen via Alpine/Leaflet faalt: ${tekenResult.reason ?? ''}`).toBe(true);

    // Wacht op livewire-commit + state-save naar Draft.
    await page.waitForTimeout(2500);

    const voorReload = await aantalGetekendeShapesOpEersteKaart(page);
    expect(voorReload, 'na tekenen moet er minstens 1 path op de map staan').toBeGreaterThan(0);

    // ---------- Refresh ----------------------------------------------
    await page.reload({ waitUntil: 'networkidle' });

    // Wacht tot de Locatie-stap weer in beeld is en de map opnieuw geinit.
    expect(await huidigeStap(page)).toMatch(/Locatie/i);
    await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
    await page.waitForTimeout(2500);

    const naReload = await aantalGetekendeShapesOpEersteKaart(page);
    expect(naReload, 'na refresh moet de getekende polygon terug zijn — anders ben je je tekening kwijt').toBeGreaterThan(0);
});
