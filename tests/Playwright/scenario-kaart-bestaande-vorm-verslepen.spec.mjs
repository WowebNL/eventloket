import { test, expect } from '@playwright/test';
import { verseStart, stap1Contactgegevens, stap2HetEvenement } from './helpers/wizard-flow.mjs';
import { huidigeStap, endsWithSelector } from './helpers/form-invullen.mjs';
import { tekenLijnOpKaart, tekenPolygonOpKaart } from './helpers/map-tekenen.mjs';

/**
 * Regression: editing a shape that was already on the map when the page
 * loaded (a resumed draft, a reused application, a reload) must be saved.
 *
 * Shapes loaded from the state live in the map's own FeatureGroup. Geoman
 * fires `pm:edit` on the edited layer and its parent groups, not on the map,
 * so a listener on the map never sees the edit and the state keeps the old
 * geometry while the map shows the new one.
 *
 * The scenario draws a shape, reloads so the shape comes back from the
 * state, drags one vertex with the mouse in edit mode, and then checks both
 * the Livewire state and a second reload for the new geometry.
 *
 * A second variant drags the vertex right after drawing, without the first
 * reload. That path already worked; it guards the sync of shapes drawn in
 * the current session, which relies on pm:create adding them to the same
 * FeatureGroup.
 */

/**
 * Geometry of the first feature in the state of the first map on the page,
 * read through the map's own state path (its `wire:key`).
 */
async function geometrieInState(page) {
    return page.evaluate(() => {
        const container = document.querySelector('.leaflet-container');
        const host = container && container.closest('[wire\\:key^="osm-map-picker-"]');
        if (! host) return null;
        const statePath = host.getAttribute('wire:key').replace(/^osm-map-picker-/, '');
        const root = host.closest('[wire\\:id]');
        const component = root && window.Livewire && window.Livewire.find(root.getAttribute('wire:id'));
        if (! component) return null;
        const value = component.get(statePath);
        const features = value && value.geojson && value.geojson.features;
        return features && features.length ? features[0].geometry : null;
    });
}

/** Geometry of the first shape the first map rendered from the state. */
async function geometrieOpKaart(page) {
    return page.evaluate(() => {
        const container = document.querySelector('.leaflet-container');
        let el = container;
        while (el && ! el.__featureGroup) {
            el = el.parentElement;
        }
        if (! el) return null;
        const features = el.__featureGroup.toGeoJSON().features;
        return features.length ? features[0].geometry : null;
    });
}

async function wachtOpKaart(page) {
    await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
    // The blade loads the shapes from the state after a short delay.
    await expect.poll(() => geometrieOpKaart(page), { timeout: 10_000 }).not.toBeNull();
}

async function versleepEersteHoekpunt(page) {
    const container = page.locator('.leaflet-container').first();
    await container.scrollIntoViewIfNeeded();

    // Fit the map to the shape and zoom out one step, so the vertices are
    // far enough apart and well inside the map (a freshly drawn shape is not
    // fitted yet). Then turn on edit mode, the same as clicking the edit
    // button in the toolbar.
    await page.evaluate(async () => {
        let el = document.querySelector('.leaflet-container');
        while (el && ! el.__leafletMap) {
            el = el.parentElement;
        }
        const map = el.__leafletMap;
        map.fitBounds(el.__featureGroup.getBounds(), { animate: false });
        await new Promise((resolve) => {
            map.once('zoomend', resolve);
            map.setZoom(map.getZoom() - 1, { animate: false });
        });
        map.pm.enableGlobalEditMode();
    });
    await container.scrollIntoViewIfNeeded();

    const hoekpunt = container.locator('.marker-icon:not(.marker-icon-middle)').first();
    await hoekpunt.waitFor({ state: 'visible', timeout: 5_000 });
    const box = await hoekpunt.boundingBox();
    const x = box.x + box.width / 2;
    const y = box.y + box.height / 2;

    await page.mouse.move(x, y);
    await page.mouse.down();
    await page.mouse.move(x + 15, y + 10, { steps: 5 });
    await page.mouse.move(x + 30, y + 20, { steps: 5 });
    await page.mouse.up();
}

const scenarios = [
    {
        naam: 'route',
        keuze: /Op een route/i,
        teken: (page) => tekenLijnOpKaart(page, 0),
    },
    {
        naam: 'area',
        keuze: /Buiten op één of meerdere plaatsen/i,
        teken: (page) => tekenPolygonOpKaart(page, 0),
    },
];

const varianten = [
    { naHerladen: true, titel: (naam) => `map: dragging a vertex of an existing ${naam} is saved` },
    { naHerladen: false, titel: (naam) => `map: dragging a vertex of a ${naam} drawn in the same session is saved` },
];

for (const scenario of scenarios) for (const variant of varianten) {
    test(variant.titel(scenario.naam), async ({ page }) => {
        test.setTimeout(120_000);

        await verseStart(page);
        // The contact details are prefilled from the profile when it has
        // them; fill them here so the scenario does not depend on the seed.
        for (const [veld, waarde] of [['watIsUwVoornaam', 'Test'], ['watIsUwAchternaam', 'Organiser'], ['watIsUwTelefoonnummer', '0612345678']]) {
            const input = page.locator(endsWithSelector('input', `data.${veld}`)).first();
            if (await input.count() > 0 && (await input.inputValue()) === '') {
                await input.fill(waarde);
            }
        }
        await stap1Contactgegevens(page);
        await stap2HetEvenement(page, { naam: `Edit ${scenario.naam}` });

        expect(await huidigeStap(page)).toMatch(/Locatie/i);
        await page.getByRole('checkbox', { name: scenario.keuze }).check();
        await page.locator('.leaflet-container').first().waitFor({ state: 'visible', timeout: 10_000 });
        await page.waitForTimeout(800);

        const tekenResult = await scenario.teken(page);
        expect(tekenResult.ok, `drawing failed: ${tekenResult.reason ?? ''}`).toBe(true);
        await expect.poll(() => geometrieInState(page), { timeout: 10_000 }).not.toBeNull();

        if (variant.naHerladen) {
            // Reload so the shape is an existing one, loaded from the state.
            await page.reload({ waitUntil: 'networkidle' });
            expect(await huidigeStap(page)).toMatch(/Locatie/i);
        }
        await wachtOpKaart(page);

        const origineel = await geometrieInState(page);
        expect(origineel, 'the drawn shape must be in the state').not.toBeNull();
        expect(await geometrieOpKaart(page)).toEqual(origineel);

        await versleepEersteHoekpunt(page);

        // The map shows the moved vertex in any case.
        const opKaart = await geometrieOpKaart(page);
        expect(opKaart, 'the drag must change the shape on the map').not.toEqual(origineel);

        // The state must follow the map.
        await expect
            .poll(() => geometrieInState(page), { timeout: 10_000, message: 'the edited shape must reach the state' })
            .toEqual(opKaart);

        // And it must survive another reload, so it was stored server side.
        await page.reload({ waitUntil: 'networkidle' });
        expect(await huidigeStap(page)).toMatch(/Locatie/i);
        await wachtOpKaart(page);
        expect(await geometrieOpKaart(page)).toEqual(opKaart);
    });
}
