import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import {
    vulTekst,
    vulTextarea,
    kiesSelect,
    klikVolgende,
    huidigeStap,
} from './helpers/form-invullen.mjs';
import {
    tekenPolygonOpKaart,
    tekenLijnOpKaart,
    aantalShapesOpKaart,
    aantalFeaturesInLivewireState,
    geometryTypesInLivewireState,
} from './helpers/map-tekenen.mjs';

/**
 * End-to-end matrix-tests voor de osm-map-picker na de Alpine
 * state-collision-fix. Dekt:
 *
 * - 1 kaart, 1 polygon, reload → polygon overleeft
 * - 1 kaart, 2 polygons, reload → beide polygons overleven
 * - 2 kaarten, polygon op upper + lijn op lower → geen cross-contamination
 *   (upper-state heeft alleen Polygon, lower-state alleen LineString)
 * - 2 kaarten + Volgende-klik direct na tekenen → state staat persistent
 *   in DB voor de step-change (saveDraftNow-pad)
 * - Wizard navigatie heen-en-terug behoudt tekeningen op beide kaarten
 */

/** Leeg de Draft-DB voor de test-organisator. */
function leegDraftDbVoorTestUser() {
    execSync(
        './vendor/bin/sail exec laravel.test php -r \'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); \\App\\EventForm\\Persistence\\Draft::whereHas("user", fn ($q) => $q->where("email", "noah.degraaf@example.net"))->delete();\'',
        { stdio: 'pipe', timeout: 30_000 },
    );
}

/**
 * Doorloop stap 1 (contactgegevens) + stap 2 (evenement) zodat we op
 * stap 3 (Locatie) staan. Eén centrale helper zodat elke test dezelfde
 * basis-route volgt.
 */
async function ganaarLocatieStap(page) {
    await loginAlsOrganiser(page);
    await openFormulier(page);

    // ---------- Stap 1: Contactgegevens -----------
    await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
    await vulTekst(page, 'huisnummer1', '1').catch(() => {});
    await vulTekst(page, 'straatnaam1', 'Marktplein').catch(() => {});
    await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
    await klikVolgende(page);

    // ---------- Stap 2: Evenement -----------
    expect(await huidigeStap(page)).toMatch(/Het evenement/i);
    await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Map-isolatie test');
    await page.waitForTimeout(600);
    await vulTextarea(
        page,
        'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning',
        'Test-omschrijving voor map-isolation scenarios.',
    );
    await kiesSelect(page, 'soortEvenement', 'Festival');
    await page.waitForTimeout(500);
    await klikVolgende(page);

    // ---------- Stap 3: Locatie ----------
    expect(await huidigeStap(page)).toMatch(/Locatie/i);
}

async function activeerBuitenLocatie(page) {
    await page.getByRole('checkbox', { name: /Buiten op één of meerdere plaatsen/i }).check();
    await page.waitForTimeout(1200);
    await page.getByLabel('Naam van de locatie').fill('Festivalweide').catch(() => {});
    await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
    await page.waitForTimeout(800);
}

async function activeerRoute(page) {
    await page.getByRole('checkbox', { name: /Op een route/i }).check();
    await page.waitForTimeout(1200);
    await page.getByLabel('Naam van de route').fill('Optochtroute').catch(() => {});
    // Wacht tot tweede leaflet-container ook in beeld is.
    await page.waitForFunction(
        () => document.querySelectorAll('.leaflet-container').length >= 2,
        null,
        { timeout: 10_000 },
    );
    await page.waitForTimeout(800);
}

const POLY_A = [[50.853, 5.690], [50.858, 5.690], [50.858, 5.700], [50.853, 5.700]];
const POLY_B = [[50.860, 5.710], [50.864, 5.710], [50.864, 5.720], [50.860, 5.720]];
const LIJN_A = [[50.853, 5.690], [50.860, 5.700], [50.865, 5.720]];

test.describe('osm-map-picker: state-isolatie en persistentie', () => {
    test.beforeEach(() => {
        leegDraftDbVoorTestUser();
    });

    test('1 kaart, 1 polygon → reload behoudt de polygon', async ({ page }) => {
        test.setTimeout(120_000);
        await ganaarLocatieStap(page);
        await activeerBuitenLocatie(page);

        const t = await tekenPolygonOpKaart(page, 0, POLY_A);
        expect(t.ok, `tekenen faalt: ${t.reason ?? ''}`).toBe(true);

        // Wacht op syncToState → $wire.$commit() → DraftStore::save.
        await page.waitForTimeout(2500);

        expect(await aantalFeaturesInLivewireState(page, 'locatieSOpKaart')).toBe(1);
        expect(await geometryTypesInLivewireState(page, 'locatieSOpKaart')).toEqual(['Polygon']);

        await page.reload({ waitUntil: 'networkidle' });
        expect(await huidigeStap(page)).toMatch(/Locatie/i);
        await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
        await page.waitForTimeout(1500);

        expect(await aantalShapesOpKaart(page, 0)).toBeGreaterThan(0);
        expect(await aantalFeaturesInLivewireState(page, 'locatieSOpKaart')).toBe(1);
    });

    test('1 kaart, 2 polygons → reload behoudt beide polygons', async ({ page }) => {
        test.setTimeout(120_000);
        await ganaarLocatieStap(page);
        await activeerBuitenLocatie(page);

        let t = await tekenPolygonOpKaart(page, 0, POLY_A);
        expect(t.ok, `eerste polygon faalt: ${t.reason ?? ''}`).toBe(true);
        await page.waitForTimeout(1500);

        t = await tekenPolygonOpKaart(page, 0, POLY_B);
        expect(t.ok, `tweede polygon faalt: ${t.reason ?? ''}`).toBe(true);
        await page.waitForTimeout(2500);

        expect(await aantalFeaturesInLivewireState(page, 'locatieSOpKaart')).toBe(2);
        const types = await geometryTypesInLivewireState(page, 'locatieSOpKaart');
        expect(types).toEqual(['Polygon', 'Polygon']);

        await page.reload({ waitUntil: 'networkidle' });
        await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
        await page.waitForTimeout(1500);

        expect(await aantalFeaturesInLivewireState(page, 'locatieSOpKaart')).toBe(2);
        expect(await aantalShapesOpKaart(page, 0)).toBeGreaterThanOrEqual(2);
    });

    test('2 kaarten: polygon op upper + lijn op lower → state per kaart is geïsoleerd', async ({ page }) => {
        test.setTimeout(150_000);
        await ganaarLocatieStap(page);
        await activeerBuitenLocatie(page);
        await activeerRoute(page);

        let t = await tekenPolygonOpKaart(page, 0, POLY_A);
        expect(t.ok, `polygon op kaart 0 faalt: ${t.reason ?? ''}`).toBe(true);
        await page.waitForTimeout(1500);

        t = await tekenLijnOpKaart(page, 1, LIJN_A);
        expect(t.ok, `lijn op kaart 1 faalt: ${t.reason ?? ''}`).toBe(true);
        await page.waitForTimeout(2500);

        // Kern-assertie: geen cross-contamination tussen state-paden.
        expect(await geometryTypesInLivewireState(page, 'locatieSOpKaart')).toEqual(['Polygon']);
        expect(await geometryTypesInLivewireState(page, 'routesOpKaart')).toEqual(['LineString']);

        await page.reload({ waitUntil: 'networkidle' });
        await page.waitForFunction(
            () => document.querySelectorAll('.leaflet-container').length >= 2,
            null,
            { timeout: 10_000 },
        );
        await page.waitForTimeout(2000);

        expect(await geometryTypesInLivewireState(page, 'locatieSOpKaart')).toEqual(['Polygon']);
        expect(await geometryTypesInLivewireState(page, 'routesOpKaart')).toEqual(['LineString']);
        // Visueel: elke kaart toont alleen zijn eigen feature.
        expect(await aantalShapesOpKaart(page, 0)).toBeGreaterThan(0);
        expect(await aantalShapesOpKaart(page, 1)).toBeGreaterThan(0);
    });

    test('Volgende-klik direct na tekening: saveDraftNow persisteert binnen 10s-throttle', async ({ page }) => {
        test.setTimeout(150_000);
        await ganaarLocatieStap(page);
        await activeerBuitenLocatie(page);

        const t = await tekenPolygonOpKaart(page, 0, POLY_A);
        expect(t.ok, `tekenen faalt: ${t.reason ?? ''}`).toBe(true);

        // GEEN bewuste wait — we testen juist dat Volgende ZELF zorgt
        // dat de tekening alsnog wordt opgeslagen, ook al ligt deze
        // binnen de 10s draft-save-throttle.
        await page.waitForTimeout(400);
        await klikVolgende(page);

        // Reload op de volgende stap; ga met "Vorige" terug naar Locatie
        // en verifieer dat de polygon nog in de state staat.
        await page.reload({ waitUntil: 'networkidle' });
        await page.waitForTimeout(1500);

        const huidige = await huidigeStap(page);
        // Wizard kan op stap 4 (Tijden) of stap 5 (Vooraankondiging) staan
        // afhankelijk van applicability. We zijn in elk geval voorbij stap 3.
        expect(huidige).not.toMatch(/Locatie/i);

        // Klik terug naar Locatie via de Vorige-knop.
        await page.getByRole('button', { name: /^vorige$/i }).first().click();
        await page.waitForTimeout(1500);
        expect(await huidigeStap(page)).toMatch(/Locatie/i);

        await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
        await page.waitForTimeout(1500);

        expect(await aantalFeaturesInLivewireState(page, 'locatieSOpKaart')).toBe(1);
        expect(await aantalShapesOpKaart(page, 0)).toBeGreaterThan(0);
    });

    test('Wizard navigatie heen-en-terug behoudt tekeningen op beide kaarten', async ({ page }) => {
        test.setTimeout(180_000);
        await ganaarLocatieStap(page);
        await activeerBuitenLocatie(page);
        await activeerRoute(page);

        let t = await tekenPolygonOpKaart(page, 0, POLY_A);
        expect(t.ok, `polygon op kaart 0 faalt: ${t.reason ?? ''}`).toBe(true);
        await page.waitForTimeout(1200);

        t = await tekenLijnOpKaart(page, 1, LIJN_A);
        expect(t.ok, `lijn op kaart 1 faalt: ${t.reason ?? ''}`).toBe(true);
        await page.waitForTimeout(2000);

        // Naar voren
        await klikVolgende(page);
        expect(await huidigeStap(page)).not.toMatch(/Locatie/i);

        // Naar achteren
        await page.getByRole('button', { name: /^vorige$/i }).first().click();
        await page.waitForTimeout(1500);
        expect(await huidigeStap(page)).toMatch(/Locatie/i);

        await page.waitForFunction(
            () => document.querySelectorAll('.leaflet-container').length >= 2,
            null,
            { timeout: 10_000 },
        );
        await page.waitForTimeout(1500);

        expect(await geometryTypesInLivewireState(page, 'locatieSOpKaart')).toEqual(['Polygon']);
        expect(await geometryTypesInLivewireState(page, 'routesOpKaart')).toEqual(['LineString']);
        expect(await aantalShapesOpKaart(page, 0)).toBeGreaterThan(0);
        expect(await aantalShapesOpKaart(page, 1)).toBeGreaterThan(0);
    });
});
