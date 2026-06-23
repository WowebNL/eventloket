/**
 * Helpers om programmatisch op de osm-map-picker te tekenen zonder de
 * GeoMan-toolbar aan te klikken (te flaky in headless). Werkt via de
 * `__leafletMap`-property die onze blade-override op de map-host-DIV
 * zet (zie resources/views/vendor/map-picker/fields/osm-map-picker.blade.php).
 *
 * Sinds de Alpine state-collision-fix is `$data.map` niet meer betrouwbaar
 * per kaart — meerdere Map::make()-velden op dezelfde Livewire-pagina
 * delen dat object. We pakken de Leaflet-instance daarom per DOM-element.
 */

/**
 * @private Vindt de Leaflet-map die bij de N-de leaflet-container in
 * de DOM hoort, via de `__leafletMap`-stash op de host-DIV.
 */
function pakMapVoorContainer(containerIndex) {
    const containers = Array.from(document.querySelectorAll('.leaflet-container'));
    const container = containers[containerIndex];
    if (! container) {
        return { ok: false, reason: `geen leaflet-container met index ${containerIndex}` };
    }

    let el = container;
    while (el && ! el.__leafletMap) {
        el = el.parentElement;
    }
    if (! el || ! el.__leafletMap) {
        return { ok: false, reason: 'geen __leafletMap op host-DIV (fix niet actief?)' };
    }

    return { ok: true, map: el.__leafletMap };
}

/**
 * Teken een polygon op de N-de kaart (0 = bovenste, 1 = volgende).
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} kaartIndex
 * @param {Array<[number, number]>} coords  Lat/lng-paren, minimaal 3.
 * @returns {Promise<{ok: true} | {ok: false, reason: string}>}
 */
export async function tekenPolygonOpKaart(page, kaartIndex, coords = [
    [50.853, 5.690],
    [50.858, 5.690],
    [50.858, 5.700],
    [50.853, 5.700],
]) {
    return page.evaluate(async ({ kaartIndex, coords, pakMapVoorContainerSrc }) => {
        const pakMap = new Function('return ' + pakMapVoorContainerSrc)();
        const got = pakMap(kaartIndex);
        if (! got.ok) return got;
        if (! window.L) return { ok: false, reason: 'Leaflet niet aanwezig' };

        const layer = window.L.polygon(coords);
        got.map.fire('pm:create', { layer, shape: 'Polygon' });
        // Wacht op de pm:create-handler's async $wire.set + $commit; fire()
        // is zelf synchroon. Map::make() rendert wire:model.deferred, dus
        // zonder dit zou de polygon nooit naar de server gaan vóór een
        // reload of vervolgactie in de test.
        await new Promise((r) => setTimeout(r, 50));
        const wireEl = document.querySelector('[wire\\:id]');
        if (wireEl && window.Livewire) {
            const c = window.Livewire.find(wireEl.getAttribute('wire:id'));
            if (c && c.$wire && typeof c.$wire.$commit === 'function') {
                await c.$wire.$commit();
            } else if (c && typeof c.$commit === 'function') {
                await c.$commit();
            }
        }
        return { ok: true };
    }, { kaartIndex, coords, pakMapVoorContainerSrc: pakMapVoorContainer.toString() });
}

/**
 * Teken een lijn (polyline) op de N-de kaart.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} kaartIndex
 * @param {Array<[number, number]>} coords  Lat/lng-paren, minimaal 2.
 */
export async function tekenLijnOpKaart(page, kaartIndex, coords = [
    [50.853, 5.690],
    [50.860, 5.700],
    [50.865, 5.720],
]) {
    return page.evaluate(async ({ kaartIndex, coords, pakMapVoorContainerSrc }) => {
        const pakMap = new Function('return ' + pakMapVoorContainerSrc)();
        const got = pakMap(kaartIndex);
        if (! got.ok) return got;
        if (! window.L) return { ok: false, reason: 'Leaflet niet aanwezig' };

        const layer = window.L.polyline(coords);
        got.map.fire('pm:create', { layer, shape: 'Line' });
        await new Promise((r) => setTimeout(r, 50));
        const wireEl = document.querySelector('[wire\\:id]');
        if (wireEl && window.Livewire) {
            const c = window.Livewire.find(wireEl.getAttribute('wire:id'));
            if (c && c.$wire && typeof c.$wire.$commit === 'function') {
                await c.$wire.$commit();
            } else if (c && typeof c.$commit === 'function') {
                await c.$commit();
            }
        }
        return { ok: true };
    }, { kaartIndex, coords, pakMapVoorContainerSrc: pakMapVoorContainer.toString() });
}

/**
 * Telt het aantal SVG-paths in de overlay-pane van de N-de kaart. Eén
 * path per getekend feature (polygon of polyline).
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} kaartIndex
 * @returns {Promise<number>}
 */
export async function aantalShapesOpKaart(page, kaartIndex) {
    return page.evaluate((kaartIndex) => {
        const containers = Array.from(document.querySelectorAll('.leaflet-container'));
        const container = containers[kaartIndex];
        if (! container) return 0;
        const overlay = container.querySelector('.leaflet-overlay-pane svg');
        if (! overlay) return 0;
        return overlay.querySelectorAll('path').length;
    }, kaartIndex);
}

/**
 * @private Vindt de Livewire-page-component (EventFormPage) op de pagina
 * via een input met wire:model^="data." Loopt vandaaruit omhoog naar
 * de dichtsbijzijnde [wire:id] root en haalt die proxy op via
 * Livewire.find(). Werkt voor zowel Livewire 3 als oudere versies.
 *
 * Retourneert null wanneer geen geschikt element bestaat.
 */
function vindFormPageComponent() {
    if (! window.Livewire) return null;
    const dataInput = document.querySelector('input[wire\\:model^="data."], textarea[wire\\:model^="data."], select[wire\\:model^="data."], input[wire\\:model\\.live^="data."], textarea[wire\\:model\\.live^="data."], select[wire\\:model\\.live^="data."]');
    if (! dataInput) return null;
    const root = dataInput.closest('[wire\\:id]');
    if (! root) return null;
    const id = root.getAttribute('wire:id');
    if (! id) return null;
    return window.Livewire.find(id);
}

/**
 * Telt het aantal features in de Livewire-state voor een specifiek
 * map-veld. Geeft 0 als de state nog leeg is. Gebruikt voor end-to-end
 * verificatie dat een tekening daadwerkelijk naar de server is gesynct
 * (en dus bij reload terug komt).
 *
 * @param {import('@playwright/test').Page} page
 * @param {'locatieSOpKaart' | 'routesOpKaart'} veldNaam
 * @returns {Promise<number>}
 */
export async function aantalFeaturesInLivewireState(page, veldNaam) {
    return page.evaluate(({ veldNaam, vindSrc }) => {
        const vind = new Function('return ' + vindSrc)();
        const component = vind();
        if (! component) return 0;
        const value = component.get('data.' + veldNaam);
        if (! value || ! value.geojson || ! Array.isArray(value.geojson.features)) return 0;
        return value.geojson.features.length;
    }, { veldNaam, vindSrc: vindFormPageComponent.toString() });
}

/**
 * Geometry-types van de features in een specifiek map-veld. Handig om
 * te verifiëren dat een upper-tekening niet als LineString in de
 * lower-state belandt (of vice versa).
 *
 * @param {import('@playwright/test').Page} page
 * @param {'locatieSOpKaart' | 'routesOpKaart'} veldNaam
 * @returns {Promise<string[]>}
 */
export async function geometryTypesInLivewireState(page, veldNaam) {
    return page.evaluate(({ veldNaam, vindSrc }) => {
        const vind = new Function('return ' + vindSrc)();
        const component = vind();
        if (! component) return [];
        const value = component.get('data.' + veldNaam);
        if (! value || ! value.geojson || ! Array.isArray(value.geojson.features)) return [];
        return value.geojson.features.map((f) => (f.geometry && f.geometry.type) || '?');
    }, { veldNaam, vindSrc: vindFormPageComponent.toString() });
}

// Backwards-compat aliases zodat bestaande scenarios blijven werken
// totdat we ze opschonen.
export const tekenPolygonOpEersteKaart = (page, coords) => tekenPolygonOpKaart(page, 0, coords);
export const tekenLijnOpEersteKaart = (page, coords) => tekenLijnOpKaart(page, 0, coords);
export const aantalGetekendeShapesOpEersteKaart = (page) => aantalShapesOpKaart(page, 0);
