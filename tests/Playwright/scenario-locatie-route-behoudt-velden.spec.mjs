import { test, expect } from '@playwright/test';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import { leegDraftDb } from './helpers/wizard-flow.mjs';
import {
    vulTekst,
    vulTextarea,
    vulEindigendOp,
    kiesSelect,
    klikVolgende,
    huidigeStap,
} from './helpers/form-invullen.mjs';
import {
    tekenLijnOpEersteKaart,
} from './helpers/map-tekenen.mjs';

/**
 * Bug-reproductie: race tussen route-tekenen en typen.
 *
 * Volgorde uit Dion's beschrijving:
 *  1. Gebruiker tekent een lijn voor de route op de kaart. De map-Alpine
 *     committeert direct via `$wire.$commit()` → server start gemeente-
 *     bepaling (kost een paar honderd ms tot enkele seconden).
 *  2. Gebruiker begint óndertussen tekst-velden onder de kaart in te
 *     vullen (naam, soort route, omschrijving).
 *  3. Server-response komt binnen, Livewire her-rendert het Filament-
 *     formulier — en de net ingevulde maar nóg-niet-gecommitte
 *     input-waarden gaan verloren.
 *
 * Reproductie: `fill()` zonder Tab/blur erna → DOM heeft de waarde maar
 * Livewire-state nog niet. Lange wait daarna geeft de re-render tijd om
 * de input-waarde plat te trappen.
 */
test('locatie: route-lijn + tegelijk velden invullen → gemeente-response mag de velden niet leegmaken', async ({ page }) => {
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
    await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Route-race-test');
    await page.waitForTimeout(800);
    await vulTextarea(page, 'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning', 'Test omschrijving voor route-race-scenario.');
    await kiesSelect(page, 'soortEvenement', 'Festival');
    await page.waitForTimeout(500);
    await klikVolgende(page);

    // ---------- Stap 3: Locatie — kies route ------------------------
    expect(await huidigeStap(page)).toMatch(/Locatie/i);
    await page.getByRole('checkbox', { name: /Op een route/i }).check();
    await page.waitForTimeout(1500);

    // Open de routes-op-kaart repeater zodat de kaart verschijnt.
    const toevoegRouteKnop = page.getByRole('button', { name: /Toevoegen aan Route op kaart/i }).first();
    if (await toevoegRouteKnop.count() > 0) {
        await toevoegRouteKnop.click();
        await page.waitForTimeout(1500);
    }

    // Wacht tot leaflet daadwerkelijk gerenderd is.
    await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
    await page.waitForTimeout(800);

    // ---------- Teken de lijn (triggert async gemeente-detect) ------
    // De pm:create-handler in osm-map-picker.blade doet meteen een
    // queueMicrotask → $wire.$commit(). Server gaat aan de gemeente
    // rekenen; response volgt asynchroon.
    const tekenResult = await tekenLijnOpEersteKaart(page);
    expect(tekenResult.ok, `lijn-tekenen via Alpine/Leaflet faalt: ${tekenResult.reason ?? ''}`).toBe(true);

    // ---------- Direct daarna: vul velden in zonder te blurren -----
    // Korte pauze zodat $wire.$commit echt onderweg is; níet wachten op
    // de response. We willen het race-window raken.
    await page.waitForTimeout(200);

    const verwachteRouteNaam = 'Carnavalsoptocht Heerlen 2026';
    const verwachteOmschrijving = 'Lange route door het centrum van Heerlen, langs alle horeca.';

    // `fill()` zonder Tab/blur → DOM heeft de waarde, Livewire-state nog niet.
    const naamInput = page.locator('input[wire\\:model$=".naamVanDeRoute"]').first();
    await naamInput.fill(verwachteRouteNaam);

    const omschrijvingTextarea = page.locator('textarea[wire\\:model$=".welkSoortRouteEvenementBetreftUwEvenementX"]').first();
    if (await omschrijvingTextarea.count() > 0) {
        await omschrijvingTextarea.fill(verwachteOmschrijving);
    }

    // ---------- Wacht op gemeente-response → re-render --------------
    // 8s is ruim voldoende voor de Kadaster/Locatieserver-roundtrip op
    // local dev. We willen dat de Livewire-her-render compleet door is
    // gegaan vóór we asserten.
    await page.waitForTimeout(8000);

    // ---------- Assertie: velden hebben hun waarde nog --------------
    await expect(naamInput, 'naamVanDeRoute mag niet leeggemaakt zijn door de gemeente-response re-render').toHaveValue(verwachteRouteNaam);

    if (await omschrijvingTextarea.count() > 0) {
        await expect(omschrijvingTextarea, 'welkSoortRouteEvenementBetreftUwEvenementX mag niet leeggemaakt zijn door de gemeente-response re-render').toHaveValue(verwachteOmschrijving);
    }
});
