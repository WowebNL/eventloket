import { test, expect, request as playwrightRequest } from '@playwright/test';
import { loginAlsOrganiser } from './helpers/login.mjs';
import { endsWithSelector, klikVolgende } from './helpers/form-invullen.mjs';

/**
 * Regression: a map that already holds a shape when the form loads must
 * render once its wizard step is opened.
 *
 * "Repeat request" opens a new draft, prefilled from an earlier zaak, on the
 * first wizard step. The location step is in the DOM but hidden at that
 * point, so the map that holds the prefilled route or area is created inside
 * a container without a size. Leaflet only measures its container on a
 * window resize, so after moving to the location step it kept the empty
 * size: no tiles, the shape fitted at the minimum zoom, and a grey area
 * until the page was reloaded.
 *
 * The scenario seeds a zaak with a route or an area, starts a new request
 * from it, moves to the location step and checks that the map knows its real
 * size, loads tiles and shows the shape.
 */

/** State of the first map on the page, read from the Leaflet instance. */
async function kaartToestand(page) {
    return page.evaluate(() => {
        const container = document.querySelector('.leaflet-container');
        let el = container;
        while (el && ! el.__leafletMap) {
            el = el.parentElement;
        }
        if (! el || ! el.__featureGroup) return null;
        const map = el.__leafletMap;
        const size = map.getSize();
        const shapeBounds = el.__featureGroup.getBounds();
        return {
            leafletWidth: size.x,
            leafletHeight: size.y,
            domWidth: container.clientWidth,
            domHeight: container.clientHeight,
            zoom: map.getZoom(),
            minZoom: map.getMinZoom(),
            shapeInView: shapeBounds.isValid() && map.getBounds().contains(shapeBounds),
            loadedTiles: container.querySelectorAll('.leaflet-tile-loaded').length,
        };
    });
}

const scenarios = [
    { naam: 'route', location: 'route', keuze: /Op een route/i },
    { naam: 'area', location: 'area', keuze: /Buiten op één of meerdere plaatsen/i },
];

for (const scenario of scenarios) {
    test(`map: a prefilled ${scenario.naam} renders on the location step of a repeated request`, async ({ page }, testInfo) => {
        test.setTimeout(90_000);

        await loginAlsOrganiser(page);
        const tenant = new URL(page.url()).pathname.split('/')[2];

        const baseUrl = process.env.EF_BASE_URL || 'http://localhost';
        const ctx = await playwrightRequest.newContext({ baseURL: baseUrl });
        // Every run adds a draft; start empty so the draft limit is never hit.
        await ctx.post('/_test/reset-draft', { form: { email: 'noah.degraaf@example.net' }, timeout: 10_000 });
        const resp = await ctx.post('/_test/seed-prefill-zaak', {
            form: { email: 'noah.degraaf@example.net', location: scenario.location },
            timeout: 10_000,
        });
        expect(resp.ok(), 'the seed endpoint must create a zaak').toBeTruthy();
        const { zaak_id: zaakId } = await resp.json();
        await ctx.dispose();

        // Exactly what the "repeat request" action on a zaak does.
        await page.goto(`/organiser/${tenant}/aanvraag?prefill_from_zaak=${zaakId}`);
        await expect(page.locator(endsWithSelector('input', '.watIsUwVoornaam')).first())
            .toHaveValue('PrefillEva', { timeout: 15_000 });

        await klikVolgende(page); // step 1 -> 2
        await page.waitForTimeout(800);
        await klikVolgende(page); // step 2 -> 3 (location)

        await expect(page.getByRole('checkbox', { name: scenario.keuze })).toBeChecked({ timeout: 10_000 });
        const container = page.locator('.leaflet-container').first();
        await container.waitFor({ state: 'visible', timeout: 10_000 });
        await container.scrollIntoViewIfNeeded();
        await expect.poll(() => kaartToestand(page), { timeout: 10_000 }).not.toBeNull();

        // Give the map the time a user would: tiles and the fit settle.
        await page.waitForTimeout(2_500);
        const toestand = await kaartToestand(page);
        await container.screenshot({ path: testInfo.outputPath(`map-${scenario.naam}.png`) });
        await testInfo.attach(`map-${scenario.naam}`, {
            path: testInfo.outputPath(`map-${scenario.naam}.png`),
            contentType: 'image/png',
        });

        expect(toestand.domWidth, 'the map container is visible').toBeGreaterThan(0);
        expect(
            { width: toestand.leafletWidth, height: toestand.leafletHeight },
            'the map must know the size of its container',
        ).toEqual({ width: toestand.domWidth, height: toestand.domHeight });
        expect(toestand.shapeInView, 'the prefilled shape must be in view').toBe(true);
        expect(toestand.zoom, 'the map must be zoomed in on the shape').toBeGreaterThan(toestand.minZoom);
        await expect.poll(
            async () => (await kaartToestand(page)).loadedTiles,
            { message: 'the map must load tiles', timeout: 10_000 },
        ).toBeGreaterThan(0);
    });
}
