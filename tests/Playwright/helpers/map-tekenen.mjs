/**
 * Helpers om programmatisch op de osm-map-picker te "tekenen" zonder
 * GeoMan-knoppen aan te klikken (in headless te flaky). We pakken het
 * Leaflet-map-object via Alpine's `_x_dataStack`, voegen een layer toe
 * aan dezelfde FeatureGroup waar dotswan's component op luistert, en
 * vuren het `pm:create` event. De bestaande `flushNu` x-init-listener
 * in osm-map-picker.blade.php committeert dan via `$wire.$commit()`.
 */

/**
 * @returns {Promise<{ ok: true } | { ok: false, reason: string }>}
 */
export async function tekenPolygonOpEersteKaart(page, coords = [
    [50.853, 5.690],
    [50.858, 5.690],
    [50.858, 5.700],
    [50.853, 5.700],
]) {
    return page.evaluate((coords) => {
        const containers = Array.from(document.querySelectorAll('.leaflet-container'));
        if (! containers.length) return { ok: false, reason: 'no leaflet container in DOM' };

        const alpineHost = containers[0].closest('[x-data]');
        if (! alpineHost || ! alpineHost._x_dataStack || ! alpineHost._x_dataStack.length) {
            return { ok: false, reason: 'no Alpine $data on map host' };
        }

        const $data = alpineHost._x_dataStack[0];
        const map = $data.map;
        if (! map) return { ok: false, reason: 'Alpine $data.map missing' };
        if (! window.L) return { ok: false, reason: 'Leaflet (window.L) missing' };

        const layer = window.L.polygon(coords);
        if ($data.drawItems && $data.drawItems.addLayer) {
            $data.drawItems.addLayer(layer);
        } else {
            layer.addTo(map);
        }

        map.fire('pm:create', { layer, shape: 'Polygon' });
        return { ok: true };
    }, coords);
}

/**
 * @returns {Promise<{ ok: true } | { ok: false, reason: string }>}
 */
export async function tekenLijnOpEersteKaart(page, coords = [
    [50.853, 5.690],
    [50.860, 5.700],
    [50.865, 5.720],
]) {
    return page.evaluate((coords) => {
        const containers = Array.from(document.querySelectorAll('.leaflet-container'));
        if (! containers.length) return { ok: false, reason: 'no leaflet container in DOM' };

        const alpineHost = containers[0].closest('[x-data]');
        if (! alpineHost || ! alpineHost._x_dataStack || ! alpineHost._x_dataStack.length) {
            return { ok: false, reason: 'no Alpine $data on map host' };
        }

        const $data = alpineHost._x_dataStack[0];
        const map = $data.map;
        if (! map) return { ok: false, reason: 'Alpine $data.map missing' };
        if (! window.L) return { ok: false, reason: 'Leaflet missing' };

        const layer = window.L.polyline(coords);
        if ($data.drawItems && $data.drawItems.addLayer) {
            $data.drawItems.addLayer(layer);
        } else {
            layer.addTo(map);
        }

        map.fire('pm:create', { layer, shape: 'Line' });
        return { ok: true };
    }, coords);
}

/**
 * Telt het aantal geometry-paths dat de eerste leaflet-map momenteel
 * rendert (polygon/polyline → SVG <path>). Werkt voor de assertion
 * "is mijn tekening (nog) zichtbaar".
 */
export async function aantalGetekendeShapesOpEersteKaart(page) {
    return page.evaluate(() => {
        const overlay = document.querySelector('.leaflet-container .leaflet-overlay-pane svg');
        if (! overlay) return 0;
        return overlay.querySelectorAll('path').length;
    });
}
