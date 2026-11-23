import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { verseStart, stap1Contactgegevens, stap2HetEvenement } from './helpers/wizard-flow.mjs';
import { endsWithSelector, huidigeStap } from './helpers/form-invullen.mjs';

/**
 * Regression: the map fields on the location step only enter the page after
 * an option is ticked, so their markup arrives through a Livewire update and
 * not with the initial page. They must still initialise, through the
 * application's map view: no page or Alpine errors, a Leaflet map inside the
 * field and the address search that the application view adds above it.
 *
 * A route read from an uploaded GPX file reaches the map state from the
 * server, so the map has to redraw itself for it to show up.
 */

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const GPX_FIXTURE = path.join(__dirname, '../Fixtures/gpx/route-two-tracks.gpx');

/** Collect uncaught page errors and Alpine expression errors. */
function verzamelFouten(page) {
    const fouten = [];
    page.on('pageerror', (error) => fouten.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (/Alpine Expression Error/i.test(message.text())) {
            fouten.push(`console.${message.type()}: ${message.text().split('\n')[0]}`);
        }
    });

    return fouten;
}

async function naarLocatieStap(page) {
    await verseStart(page);
    // The contact details are prefilled from the profile when it has them;
    // fill them here so the scenario does not depend on the seed.
    for (const [veld, waarde] of [['watIsUwVoornaam', 'Test'], ['watIsUwAchternaam', 'Organiser'], ['watIsUwTelefoonnummer', '0612345678']]) {
        const input = page.locator(endsWithSelector('input', `data.${veld}`)).first();
        if (await input.count() > 0 && (await input.inputValue()) === '') {
            await input.fill(waarde);
        }
    }
    await stap1Contactgegevens(page);
    await stap2HetEvenement(page, { naam: 'Map field test' });
    expect(await huidigeStap(page)).toMatch(/Locatie/i);
}

const scenarios = [
    { naam: 'route', keuze: /Op een route/i },
    { naam: 'area', keuze: /Buiten op één of meerdere plaatsen/i },
];

for (const scenario of scenarios) {
    test(`map: the ${scenario.naam} map initialises after its option is ticked`, { tag: '@smoke' }, async ({ page }) => {
        test.setTimeout(120_000);
        const fouten = verzamelFouten(page);

        await naarLocatieStap(page);
        await page.getByRole('checkbox', { name: scenario.keuze }).check();

        const kaart = page.locator('[x-ref="map"]').first();
        await kaart.waitFor({ state: 'attached', timeout: 15_000 });
        await kaart.scrollIntoViewIfNeeded();
        await expect(kaart).toHaveClass(/leaflet-container/, { timeout: 10_000 });
        await expect(page.getByRole('combobox', { name: 'Adres zoeken' }).first()).toBeVisible();

        await page.waitForTimeout(1_000);
        expect(fouten, fouten.join('\n')).toEqual([]);
    });
}

test('map: a route read from an uploaded GPX file is drawn on the map', { tag: '@smoke' }, async ({ page }) => {
    test.setTimeout(120_000);
    const fouten = verzamelFouten(page);

    await naarLocatieStap(page);
    await page.getByRole('checkbox', { name: /Op een route/i }).check();

    const kaart = page.locator('[x-ref="map"]').first();
    await expect(kaart).toHaveClass(/leaflet-container/, { timeout: 15_000 });
    await expect(kaart.locator('path.leaflet-interactive')).toHaveCount(0);

    const gpxVeld = page.locator('[data-field-wrapper]', { hasText: 'GPX bestand van de route' }).locator('input[type=file]').first();
    await gpxVeld.setInputFiles(GPX_FIXTURE);

    await expect(kaart.locator('path.leaflet-interactive').first()).toBeAttached({ timeout: 20_000 });
    expect(fouten, fouten.join('\n')).toEqual([]);
});

test('map: a newly drawn route is kept as drawn once the server has the state', { tag: '@smoke' }, async ({ page }) => {
    test.setTimeout(120_000);
    const fouten = verzamelFouten(page);

    await naarLocatieStap(page);
    await page.getByRole('checkbox', { name: /Op een route/i }).check();

    const kaart = page.locator('[x-ref="map"]').first();
    await expect(kaart).toHaveClass(/leaflet-container/, { timeout: 15_000 });
    await expect.poll(() => kaart.evaluate((el) => Boolean(el.__leafletMap && el.__featureGroup)), { timeout: 10_000 }).toBe(true);

    // Draw a line the way the drawing toolbar hands it over, from a view the
    // line does not fill, so a redraw that fits the shapes would move the map.
    const voor = await kaart.evaluate((el) => {
        const map = el.__leafletMap;
        map.setView([50.85, 5.69], 12, { animate: false });
        const layer = window.L.polyline([[50.853, 5.690], [50.856, 5.694], [50.858, 5.697]]);
        el.__drawnLayer = layer;
        map.fire('pm:create', { layer, shape: 'Line' });

        return { zoom: map.getZoom(), center: map.getCenter() };
    });

    // The map commits the drawn shape itself; wait until the server has it
    // and its response has been applied.
    await expect.poll(() => kaart.evaluate((el) => {
        const wire = window.Livewire.find(el.closest('[wire\\:id]').getAttribute('wire:id'));
        const state = wire.get('data.routesOpKaart');

        return state && state.geojson ? state.geojson.features.length : 0;
    }), { timeout: 15_000 }).toBe(1);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1_500);

    const na = await kaart.evaluate((el) => {
        const map = el.__leafletMap;

        return {
            zoom: map.getZoom(),
            center: map.getCenter(),
            drawnLayerKept: el.__featureGroup.hasLayer(el.__drawnLayer),
            layerCount: el.__featureGroup.getLayers().length,
        };
    });

    expect(na.drawnLayerKept, 'the drawn layer must not be replaced by a redraw').toBe(true);
    expect(na.layerCount).toBe(1);
    expect(na.zoom).toBe(voor.zoom);
    expect(na.center).toEqual(voor.center);
    expect(fouten, fouten.join('\n')).toEqual([]);
});
