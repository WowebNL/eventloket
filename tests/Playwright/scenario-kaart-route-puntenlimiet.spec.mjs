import { test, expect } from '@playwright/test';
import { verseStart, stap1Contactgegevens, stap2HetEvenement } from './helpers/wizard-flow.mjs';
import { endsWithSelector, huidigeStap } from './helpers/form-invullen.mjs';
import { schrijfGpxMetPunten } from './helpers/gpx-fixture.mjs';

/**
 * Regression: a route with many points, typically read from an uploaded GPX
 * file, hung the browser tab. Vertex editing puts a marker on every vertex
 * and between every pair of them, and turning it on costs more than linear
 * time in the number of vertices.
 *
 * Above the configured cap (maps.editing.max_points, 500) the route is still
 * drawn, but it is not made editable, the edit mode of the toolbar leaves it
 * alone, and a message under the map says how to change it instead. Below
 * the cap nothing changes. Uploading another file crosses the cap either way.
 *
 * The routes are generated per test (see helpers/gpx-fixture.mjs) and the
 * background tiles are answered locally, so no external service is involved.
 */

const BOVEN_LIMIET = 501;
const OP_LIMIET = 500;
const ONDER_LIMIET = 300;
const MELDING = 'Deze route heeft te veel punten om op de kaart aan te passen.';

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

async function naarRouteKaart(page) {
    // Background tiles are not needed to judge the shapes on the map.
    await page.route(/^https:\/\/[^/]*pdok\.nl\//, (route) => route.fulfill({ status: 204, body: '' }));

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
    await stap2HetEvenement(page, { naam: 'Route point cap' });
    expect(await huidigeStap(page)).toMatch(/Locatie/i);
    await page.getByRole('checkbox', { name: /Op een route/i }).check();

    return wachtOpKaart(page);
}

async function wachtOpKaart(page) {
    const kaart = page.locator('[x-ref="map"]').first();
    await expect(kaart).toHaveClass(/leaflet-container/, { timeout: 15_000 });
    await expect.poll(() => kaart.evaluate((el) => Boolean(el.__leafletMap && el.__featureGroup)), { timeout: 10_000 }).toBe(true);
    await kaart.scrollIntoViewIfNeeded();

    return kaart;
}

/** Number of vertices of the shapes the map has drawn. */
async function puntenOpKaart(kaart) {
    return kaart.evaluate((el) => {
        let count = 0;
        for (const feature of el.__featureGroup.toGeoJSON().features) {
            count += feature.geometry.coordinates.length;
        }

        return count;
    });
}

async function uploadGpx(page, kaart, bestand, punten) {
    const wrapper = page.locator('[data-field-wrapper]', { hasText: 'GPX bestand van de route' }).first();
    // Replace a file uploaded earlier the way a user does: remove it first.
    const verwijder = wrapper.locator('button.filepond--action-revert-item-processing:visible, button.filepond--action-remove-item:visible');
    if (await verwijder.count() > 0) {
        await verwijder.first().click();
        await expect(wrapper.locator('.filepond--item')).toHaveCount(0, { timeout: 15_000 });
        await page.waitForLoadState('networkidle');
    }
    await wrapper.locator('input[type=file]').first().setInputFiles(bestand);
    await expect.poll(() => puntenOpKaart(kaart), { timeout: 30_000 }).toBe(punten);
    await expect(kaart.locator('path.leaflet-interactive').first()).toBeAttached();
}

function melding(page) {
    return page.locator('[data-map-edit-cap-message]').first();
}

function hoekpunten(kaart) {
    return kaart.locator('.marker-icon:not(.marker-icon-middle)');
}

/** Toggle the edit mode the way a user does, with the toolbar button. */
async function klikBewerkModus(kaart) {
    await kaart.locator('a.leaflet-buttons-control-button:has(.leaflet-pm-icon-edit)').first().click();
}

/** Toggle the removal mode (the eraser) with the toolbar button. */
async function klikGumModus(kaart) {
    await kaart.locator('a.leaflet-buttons-control-button:has(.leaflet-pm-icon-delete)').first().click();
}

/** Number of features in the state of the route map. */
async function featuresInState(kaart) {
    return kaart.evaluate((el) => {
        const wire = window.Livewire.find(el.closest('[wire\\:id]').getAttribute('wire:id'));
        const state = wire.get('data.routesOpKaart');

        return state && state.geojson ? state.geojson.features.length : 0;
    });
}

test('map: a route below the point cap stays editable', { tag: '@smoke' }, async ({ page }, testInfo) => {
    test.setTimeout(120_000);
    const fouten = verzamelFouten(page);
    const kaart = await naarRouteKaart(page);

    await uploadGpx(page, kaart, schrijfGpxMetPunten(testInfo, ONDER_LIMIET), ONDER_LIMIET);
    await expect(melding(page)).toBeHidden();

    await klikBewerkModus(kaart);
    await expect(hoekpunten(kaart)).toHaveCount(ONDER_LIMIET, { timeout: 15_000 });
    expect(fouten, fouten.join('\n')).toEqual([]);
});

test('map: a route above the point cap is drawn but not editable', { tag: '@smoke' }, async ({ page }, testInfo) => {
    test.setTimeout(120_000);
    const fouten = verzamelFouten(page);
    let kaart = await naarRouteKaart(page);

    await uploadGpx(page, kaart, schrijfGpxMetPunten(testInfo, BOVEN_LIMIET), BOVEN_LIMIET);
    await expect(melding(page)).toBeVisible();
    await expect(melding(page)).toContainText(MELDING);
    await expect(hoekpunten(kaart)).toHaveCount(0);

    // The edit mode of the toolbar must leave the route alone as well.
    await klikBewerkModus(kaart);
    await page.waitForTimeout(1_000);
    await expect(hoekpunten(kaart)).toHaveCount(0);

    // Loading the stored route again takes the other path: the map draws it
    // from the state when the page opens. Count every vertex marker that is
    // ever added to the page while it loads, since one that is created and
    // removed again a moment later already costs the time.
    await expect.poll(() => kaart.evaluate((el) => {
        const wire = window.Livewire.find(el.closest('[wire\\:id]').getAttribute('wire:id'));
        const state = wire.get('data.routesOpKaart');

        return state && state.geojson ? state.geojson.features.length : 0;
    }), { timeout: 15_000 }).toBe(1);
    await page.addInitScript(() => {
        window.__vertexMarkersAdded = 0;
        new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.classList && node.classList.contains('marker-icon')) {
                        window.__vertexMarkersAdded++;
                    }
                }
            }
        }).observe(document, { childList: true, subtree: true });
    });
    await page.reload({ waitUntil: 'networkidle', timeout: 30_000 });
    kaart = await wachtOpKaart(page);
    await expect.poll(() => puntenOpKaart(kaart), { timeout: 15_000 }).toBe(BOVEN_LIMIET);
    expect(await page.evaluate(() => window.__vertexMarkersAdded), 'no vertex marker may be created while the page loads').toBe(0);
    await expect(melding(page)).toBeVisible();
    await expect(hoekpunten(kaart)).toHaveCount(0);
    await klikBewerkModus(kaart);
    await page.waitForTimeout(1_000);
    await expect(hoekpunten(kaart)).toHaveCount(0);

    expect(fouten, fouten.join('\n')).toEqual([]);
});

test('map: uploading another route crosses the point cap both ways', { tag: '@smoke' }, async ({ page }, testInfo) => {
    test.setTimeout(120_000);
    const fouten = verzamelFouten(page);
    const kaart = await naarRouteKaart(page);

    // Below the cap, with the edit mode of the toolbar on.
    await uploadGpx(page, kaart, schrijfGpxMetPunten(testInfo, ONDER_LIMIET), ONDER_LIMIET);
    await klikBewerkModus(kaart);
    await expect(hoekpunten(kaart)).toHaveCount(ONDER_LIMIET, { timeout: 15_000 });

    // Above the cap: the vertex markers go and the message shows.
    await uploadGpx(page, kaart, schrijfGpxMetPunten(testInfo, BOVEN_LIMIET), BOVEN_LIMIET);
    await expect(melding(page)).toBeVisible();
    await page.waitForTimeout(1_000);
    await expect(hoekpunten(kaart)).toHaveCount(0);

    // Back below the cap: the message goes and the route is editable again.
    await uploadGpx(page, kaart, schrijfGpxMetPunten(testInfo, ONDER_LIMIET), ONDER_LIMIET);
    await expect(melding(page)).toBeHidden();
    await expect(hoekpunten(kaart)).toHaveCount(ONDER_LIMIET, { timeout: 15_000 });

    // Exactly at the cap still counts as editable. The edit mode is turned
    // off first: turning on vertex editing for this many points takes
    // seconds, which this check does not need.
    await klikBewerkModus(kaart);
    await expect(hoekpunten(kaart)).toHaveCount(0);
    await uploadGpx(page, kaart, schrijfGpxMetPunten(testInfo, OP_LIMIET), OP_LIMIET);
    await expect(melding(page)).toBeHidden();
    expect(await kaart.evaluate((el) => el.__featureGroup.getLayers().every((layer) => layer.pm && layer.pm.options.allowEditing !== false)), 'a route at the cap must not be locked').toBe(true);
    await klikBewerkModus(kaart);
    await expect(hoekpunten(kaart)).toHaveCount(OP_LIMIET, { timeout: 15_000 });

    expect(fouten, fouten.join('\n')).toEqual([]);
});

test('map: a route above the point cap can still be removed with the eraser', { tag: '@smoke' }, async ({ page }, testInfo) => {
    test.setTimeout(120_000);
    const fouten = verzamelFouten(page);
    const kaart = await naarRouteKaart(page);

    await uploadGpx(page, kaart, schrijfGpxMetPunten(testInfo, BOVEN_LIMIET), BOVEN_LIMIET);
    await expect(melding(page)).toBeVisible();
    await expect.poll(() => featuresInState(kaart), { timeout: 15_000 }).toBe(1);

    // Erase the route the way a user does: eraser on, click the line.
    await klikGumModus(kaart);
    await kaart.locator('path.leaflet-interactive').first().click();
    await expect.poll(() => kaart.evaluate((el) => el.__featureGroup.getLayers().length), { timeout: 10_000 }).toBe(0);
    await expect.poll(() => featuresInState(kaart), { timeout: 15_000 }).toBe(0);
    await expect(melding(page)).toBeHidden();
    await klikGumModus(kaart);

    // The map is editable again: a line drawn now gets its vertex markers.
    await kaart.evaluate((el) => {
        const layer = window.L.polyline([[50.853, 5.690], [50.856, 5.694], [50.858, 5.697]]);
        el.__leafletMap.fire('pm:create', { layer, shape: 'Line' });
    });
    await expect(hoekpunten(kaart)).toHaveCount(3, { timeout: 10_000 });
    await expect(melding(page)).toBeHidden();

    expect(fouten, fouten.join('\n')).toEqual([]);
});
