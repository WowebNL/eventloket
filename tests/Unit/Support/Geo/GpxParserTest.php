<?php

declare(strict_types=1);

use App\Support\Geo\GpxParser;

function gpxFixturePath(): string
{
    return base_path('tests/Fixtures/gpx/route-two-tracks.gpx');
}

function writeGpx(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'gpx').'.gpx';
    file_put_contents($path, $contents);

    return $path;
}

test('every track segment and route becomes one line feature', function () {
    $collection = GpxParser::fromPath(gpxFixturePath());

    expect($collection)->not->toBeNull()
        ->and($collection['type'])->toBe('FeatureCollection')
        ->and($collection['features'])->toHaveCount(4);

    $types = array_map(fn (array $feature): string => $feature['geometry']['type'], $collection['features']);
    expect($types)->toBe(['LineString', 'LineString', 'LineString', 'LineString']);

    $counts = array_map(fn (array $feature): int => count($feature['geometry']['coordinates']), $collection['features']);
    expect($counts)->toBe([3, 3, 2, 2]);
});

test('a position is written as longitude then latitude', function () {
    $collection = GpxParser::fromPath(gpxFixturePath());

    expect($collection['features'][0]['geometry']['coordinates'][0])->toBe([5.6, 50.8]);
});

test('nothing but the geometry is carried over', function () {
    // The collection goes into the form state and is rendered on a map, so a
    // track name, description or timestamp from the uploaded file must not
    // travel along with it.
    $collection = GpxParser::fromPath(gpxFixturePath());

    $encoded = (string) json_encode($collection);

    expect($encoded)->not->toContain('Track one')
        ->and($encoded)->not->toContain('Planned route')
        ->and($encoded)->not->toContain('Fixture route')
        ->and($encoded)->not->toContain('2026-01-01')
        ->and($collection['features'][0]['properties'])->toEqual(new stdClass);
});

test('a standalone waypoint is not a route', function () {
    // The fixture holds one <wpt>; four features come out, all of them from
    // the two tracks and the route.
    $collection = GpxParser::fromPath(gpxFixturePath());

    expect($collection['features'])->toHaveCount(4);
});

test('a segment with a single position is dropped', function () {
    $path = writeGpx(<<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><trkseg><trkpt lat="50.85" lon="5.69"/></trkseg></trk>
</gpx>
GPX);

    expect(GpxParser::fromPath($path))->toBeNull();

    unlink($path);
});

test('a document whose gpx root carries a namespace prefix is refused', function () {
    // The upload rule looks for a literal `<gpx` root element, so a document
    // that writes its root as `<g:gpx>` never reaches the parser. The parser
    // refuses it for the same reason, which is the point: it is not allowed to
    // be wider than the rule in front of it. Exports in the wild declare the
    // GPX namespace as the default one and are read normally.
    $path = writeGpx(<<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<g:gpx version="1.1" xmlns:g="http://www.topografix.com/GPX/1/1">
  <g:trk><g:trkseg>
    <g:trkpt lat="50.85" lon="5.69"/>
    <g:trkpt lat="50.86" lon="5.70"/>
  </g:trkseg></g:trk>
</g:gpx>
GPX);

    expect(GpxParser::fromPath($path))->toBeNull();

    unlink($path);
});

test('a position outside the coordinate range is skipped', function () {
    $path = writeGpx(<<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><trkseg>
    <trkpt lat="50.85" lon="5.69"/>
    <trkpt lat="991.0" lon="5.70"/>
    <trkpt lat="50.87" lon="5.71"/>
    <trkpt lat="50.88" lon="not-a-number"/>
  </trkseg></trk>
</gpx>
GPX);

    $collection = GpxParser::fromPath($path);

    expect($collection['features'][0]['geometry']['coordinates'])->toBe([
        [5.69, 50.85],
        [5.71, 50.87],
    ]);

    unlink($path);
});

test('the parser is never wider than the upload validation', function (string $label, string $contents) {
    // The upload rule refuses these three, so the parser has to refuse them as
    // well: it is a second line of defence, not a way around the first.
    $path = writeGpx($contents);

    expect(GpxParser::fromPath($path))->toBeNull();

    unlink($path);
})->with([
    ['a document declaring a DOCTYPE', <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE gpx [<!ENTITY nothing "">]>
<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><trkseg>
    <trkpt lat="50.85" lon="5.69"/>
    <trkpt lat="50.86" lon="5.70"/>
  </trkseg></trk>
</gpx>
GPX],
    ['a document without the GPX namespace', <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1">
  <trk><trkseg>
    <trkpt lat="50.85" lon="5.69"/>
    <trkpt lat="50.86" lon="5.70"/>
  </trkseg></trk>
</gpx>
GPX],
    ['a document that is not GPX at all', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<note><to>Someone</to><body>Not a route</body></note>
XML],
]);

test('a document carrying NUL bytes is refused', function () {
    $path = writeGpx("\0".'<?xml version="1.0"?><gpx xmlns="http://www.topografix.com/GPX/1/1"/>');

    expect(GpxParser::fromPath($path))->toBeNull();

    unlink($path);
});

test('a document with more positions than the ceiling allows is refused', function () {
    $points = '';

    for ($index = 0; $index <= GpxParser::MAX_POSITIONS; $index++) {
        $points .= sprintf('<trkpt lat="50.%06d" lon="5.690000"/>', $index % 1000000);
    }

    $path = writeGpx(
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">'
        .'<trk><trkseg>'.$points.'</trkseg></trk></gpx>'
    );

    expect(GpxParser::fromPath($path))->toBeNull();

    unlink($path);
});

test('a document type declaration ends the parse wherever it stands', function () {
    // The content check in front of this parser reads the start of the file
    // and nothing more, so a declaration further down is not something it can
    // see. The parser meets it while reading and stops there.
    $filler = str_repeat('x', 17000);

    $path = writeGpx(
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<!-- <gpx xmlns="http://www.topografix.com/GPX/1/1"> '.$filler.' -->'
        .'<!DOCTYPE gpx [<!ENTITY north "50.85">]>'
        .'<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">'
        .'<trk><trkseg>'
        .'<trkpt lat="&north;" lon="5.69"/>'
        .'<trkpt lat="50.86" lon="5.70"/>'
        .'</trkseg></trk>'
        .'</gpx>'
    );

    expect(GpxParser::fromPath($path))->toBeNull();

    unlink($path);
});

test('the ceiling counts position elements, not only usable ones', function () {
    // A document can be made almost entirely of position elements that carry
    // nothing usable. Reading those costs the same walk as reading real ones,
    // and that walk happens inside a request, so the ceiling counts what is
    // visited rather than what is kept.
    $unusable = str_repeat('<trkpt/>', GpxParser::MAX_POSITIONS + 1);

    $path = writeGpx(
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">'
        .'<trk>'
        .'<trkseg>'.$unusable.'</trkseg>'
        .'<trkseg><trkpt lat="50.85" lon="5.69"/><trkpt lat="50.86" lon="5.70"/></trkseg>'
        .'</trk>'
        .'</gpx>'
    );

    expect(GpxParser::fromPath($path))->toBeNull();

    unlink($path);
});

test('a missing file yields no route', function () {
    expect(GpxParser::fromPath(sys_get_temp_dir().'/does-not-exist.gpx'))->toBeNull()
        ->and(GpxParser::fromPath(''))->toBeNull();
});
