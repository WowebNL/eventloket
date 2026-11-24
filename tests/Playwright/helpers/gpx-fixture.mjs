import fs from 'node:fs';

/**
 * Write a synthetic GPX file with one track of exactly `points` positions and
 * return its path.
 *
 * The coordinates are generated, not recorded: a zigzag north from a fixed
 * start, so the line never crosses itself and the same count always yields
 * the same file.
 */
export function schrijfGpxMetPunten(testInfo, points) {
    const positions = [];
    for (let i = 0; i < points; i++) {
        const lat = (50.8 + (0.05 * i) / points).toFixed(6);
        const lon = (5.6 + (i % 2) * 0.0005).toFixed(6);
        positions.push(`<trkpt lat="${lat}" lon="${lon}"/>`);
    }

    const file = testInfo.outputPath(`route-${points}-points.gpx`);
    fs.writeFileSync(
        file,
        '<?xml version="1.0" encoding="UTF-8"?>\n'
            + '<gpx version="1.1" creator="Fixture" xmlns="http://www.topografix.com/GPX/1/1">'
            + `<trk><name>Synthetic route</name><trkseg>${positions.join('')}</trkseg></trk></gpx>\n`,
    );

    return file;
}
