<?php

use App\Models\Municipality;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

beforeEach(function () {
    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);

    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
    ]);

    $this->access_token = $response->json('access_token');
});

test('locationserver check endpoint is protected', function () {
    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => json_encode(['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]]),
    ]);

    $response->assertStatus(401);
});

test('line response contains passing municipalities excluding start and end', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'StartGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'name' => 'DoorkruistGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'name' => 'EindGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
    ]);

    // Line starts inside GM001, crosses through GM002, ends inside GM003
    $line = json_encode(['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    $passing = $response->json('data.line.passing');

    expect($passing)->toHaveCount(1);
    expect($passing[0]['brk_identification'])->toBe('GM002');
    expect($passing[0]['name'])->toBe('DoorkruistGemeente');
});

test('line passing is empty when line only touches start and end municipalities', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'StartGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'name' => 'EindGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);

    // Line starts inside GM001 and ends inside GM002, no in-between municipalities
    $line = json_encode(['type' => 'LineString', 'coordinates' => [[0, 0], [3, 0]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    $passing = $response->json('data.line.passing');

    expect($passing)->toHaveCount(0);
});

test('line passing excludes start municipality when start and end are the same', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'EnigeGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-5,-5],[5,-5],[5,5],[-5,5],[-5,-5]]]]}',
    ]);

    // Line starts and ends inside the same municipality
    $line = json_encode(['type' => 'LineString', 'coordinates' => [[-1, 0], [1, 0]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.line.start_end_equal'))->toBeTrue();
    expect($response->json('data.line.passing'))->toHaveCount(0);
});

test('lines response contains passing municipalities for each line', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'StartGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'name' => 'DoorkruistGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'name' => 'EindGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
    ]);

    $lines = json_encode([
        ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]],
    ]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'lines' => $lines,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    $linePassing = $response->json('data.lines.0.passing');
    expect($linePassing)->toHaveCount(1);
    expect($linePassing[0]['brk_identification'])->toBe('GM002');

    // Single lines entry is also mirrored to data.line
    $passing = $response->json('data.line.passing');
    expect($passing)->toHaveCount(1);
    expect($passing[0]['brk_identification'])->toBe('GM002');
});

// ============================
// LINE: start, end, items, start_end_equal
// ============================

test('line response populates items, start, end and start_end_equal', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'StartGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'name' => 'TussenGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'name' => 'EindGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
    ]);

    $line = json_encode(['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.line.items'))->toHaveCount(3);
    expect($response->json('data.line.start.brk_identification'))->toBe('GM001');
    expect($response->json('data.line.end.brk_identification'))->toBe('GM003');
    expect($response->json('data.line.start_end_equal'))->toBeFalse();
});

test('line.within is true when line is entirely within municipality geometries', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'GroteGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-10,-10],[10,-10],[10,10],[-10,10],[-10,-10]]]]}',
    ]);

    $line = json_encode(['type' => 'LineString', 'coordinates' => [[-1, 0], [1, 0]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.line.within'))->toBeTrue();
    expect($response->json('data.all.within'))->toBeTrue();
});

test('line.within is false when line extends outside municipality geometries', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'KleineGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[0,0],[5,0],[5,5],[0,5],[0,0]]]]}',
    ]);

    // Start point at -1,0.5 is outside the municipality [0..5]x[0..5]
    $line = json_encode(['type' => 'LineString', 'coordinates' => [[-1, 0.5], [3, 0.5]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.line.within'))->toBeFalse();
    expect($response->json('data.all.within'))->toBeFalse();
});

test('line populates all.items and all.object correctly', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'TestGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);

    $line = json_encode(['type' => 'LineString', 'coordinates' => [[-0.5, 0], [0.5, 0]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.all.items'))->toHaveCount(1);
    expect($response->json('data.all.items.0.brk_identification'))->toBe('GM001');

    $allObject = $response->json('data.all.object');
    expect($allObject)->toBeArray();
    expect($allObject)->toHaveKey('GM001');
    expect($allObject['GM001']['name'])->toBe('TestGemeente');
});

test('line with no intersecting municipalities returns empty items and null start, end and start_end_equal', function () {
    $line = json_encode(['type' => 'LineString', 'coordinates' => [[0, 0], [1, 0]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.line.items'))->toBeEmpty();
    expect($response->json('data.line.start'))->toBeNull();
    expect($response->json('data.line.end'))->toBeNull();
    expect($response->json('data.line.start_end_equal'))->toBeNull();
    expect($response->json('data.all.object'))->toBeNull();
});

// ============================
// LINES: multiple
// ============================

test('multiple lines each have their own items, start, end and start_end_equal', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'EersteGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'name' => 'TweedeGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
    ]);

    $lines = json_encode([
        // Line1: start and end both inside GM001
        ['type' => 'LineString', 'coordinates' => [[-0.5, 0], [0.5, 0]]],
        // Line2: from GM001 to GM002
        ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]],
    ]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'lines' => $lines,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.lines.0.items'))->toHaveCount(1);
    expect($response->json('data.lines.0.start.brk_identification'))->toBe('GM001');
    expect($response->json('data.lines.0.end.brk_identification'))->toBe('GM001');
    expect($response->json('data.lines.0.start_end_equal'))->toBeTrue();
    expect($response->json('data.lines.0.passing'))->toBeEmpty();

    expect($response->json('data.lines.1.items'))->toHaveCount(2);
    expect($response->json('data.lines.1.start.brk_identification'))->toBe('GM001');
    expect($response->json('data.lines.1.end.brk_identification'))->toBe('GM002');
    expect($response->json('data.lines.1.start_end_equal'))->toBeFalse();

    // Multiple lines do NOT mirror to data.line
    expect($response->json('data.line.items'))->toBeEmpty();
});

test('multiple lines deduplicate all.items', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'GedeeldeGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-5,-1],[5,-1],[5,1],[-5,1],[-5,-1]]]]}',
    ]);

    // Two lines both inside the same municipality
    $lines = json_encode([
        ['type' => 'LineString', 'coordinates' => [[-1, 0], [0, 0]]],
        ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 0]]],
    ]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'lines' => $lines,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.all.items'))->toHaveCount(1);
    expect($response->json('data.all.items.0.brk_identification'))->toBe('GM001');
});

// ============================
// POLYGONS
// ============================

test('polygon intersecting municipality populates polygons.items and all.items', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'TestGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);

    $polygon = ['type' => 'Polygon', 'coordinates' => [[[-0.5, -0.5], [0.5, -0.5], [0.5, 0.5], [-0.5, 0.5], [-0.5, -0.5]]]];

    $response = $this->postJson(route('api.locationserver.check'), [
        'polygons' => json_encode([$polygon]),
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.polygons.items'))->toHaveCount(1);
    expect($response->json('data.polygons.items.0.brk_identification'))->toBe('GM001');
    expect($response->json('data.all.items'))->toHaveCount(1);
    expect($response->json('data.all.object'))->toHaveKey('GM001');
});

test('polygon.within is true when polygon is entirely within municipality geometries', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'GroteGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-10,-10],[10,-10],[10,10],[-10,10],[-10,-10]]]]}',
    ]);

    $polygon = ['type' => 'Polygon', 'coordinates' => [[[-1, -1], [1, -1], [1, 1], [-1, 1], [-1, -1]]]];

    $response = $this->postJson(route('api.locationserver.check'), [
        'polygons' => json_encode([$polygon]),
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.polygons.within'))->toBeTrue();
    expect($response->json('data.all.within'))->toBeTrue();
});

test('polygon.within is false when polygon extends outside municipality geometries', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'KleineGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[0,0],[5,0],[5,5],[0,5],[0,0]]]]}',
    ]);

    // Polygon overlaps the municipality but extends outside it
    $polygon = ['type' => 'Polygon', 'coordinates' => [[[-1, -1], [2, -1], [2, 2], [-1, 2], [-1, -1]]]];

    $response = $this->postJson(route('api.locationserver.check'), [
        'polygons' => json_encode([$polygon]),
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.polygons.within'))->toBeFalse();
    expect($response->json('data.all.within'))->toBeFalse();
});

test('multiple polygons aggregate into polygons.items', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'EersteGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-2,-1],[0,-1],[0,1],[-2,1],[-2,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'name' => 'TweedeGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
    ]);

    $polygons = [
        ['type' => 'Polygon', 'coordinates' => [[[-1.5, -0.5], [-0.5, -0.5], [-0.5, 0.5], [-1.5, 0.5], [-1.5, -0.5]]]],
        ['type' => 'Polygon', 'coordinates' => [[[5.5, -0.5], [6.5, -0.5], [6.5, 0.5], [5.5, 0.5], [5.5, -0.5]]]],
    ];

    $response = $this->postJson(route('api.locationserver.check'), [
        'polygons' => json_encode($polygons),
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.polygons.items'))->toHaveCount(2);
    expect($response->json('data.all.items'))->toHaveCount(2);
});

// ============================
// ADDRESS
// ============================

test('address found via locatieserver populates addresses.items and sets within to true', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM0123',
        'name' => 'TestGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);

    Http::fake([
        'https://api.pdok.nl/*' => Http::response([
            'response' => [
                'docs' => [
                    ['gemeentecode' => '0123', 'gemeentenaam' => 'TestGemeente'],
                ],
            ],
        ]),
    ]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'address' => json_encode(['postcode' => '1234AB', 'houseNumber' => '1']),
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.addresses.within'))->toBeTrue();
    expect($response->json('data.addresses.items'))->toHaveCount(1);
    expect($response->json('data.addresses.items.0.brk_identification'))->toBe('GM0123');
    expect($response->json('data.all.within'))->toBeTrue();
    expect($response->json('data.all.items'))->toHaveCount(1);
    expect($response->json('data.all.object'))->toHaveKey('GM0123');
});

test('address not resolvable via locatieserver sets addresses.within to false', function () {
    Http::fake([
        'https://api.pdok.nl/*' => Http::response([
            'response' => ['docs' => []],
        ]),
    ]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'address' => json_encode(['postcode' => '9999ZZ', 'houseNumber' => '99']),
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.addresses.within'))->toBeFalse();
    expect($response->json('data.addresses.items'))->toBeEmpty();
    expect($response->json('data.all.within'))->toBeFalse();
    expect($response->json('data.all.object'))->toBeNull();
});

test('multiple addresses aggregate into addresses.items', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM0100',
        'name' => 'EersteGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM0200',
        'name' => 'TweedeGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
    ]);

    Http::fake([
        'https://api.pdok.nl/*' => Http::sequence()
            ->push(['response' => ['docs' => [['gemeentecode' => '0100', 'gemeentenaam' => 'EersteGemeente']]]])
            ->push(['response' => ['docs' => [['gemeentecode' => '0200', 'gemeentenaam' => 'TweedeGemeente']]]]),
    ]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'addresses' => json_encode([
            ['postcode' => '1000AA', 'houseNumber' => '1'],
            ['postcode' => '2000BB', 'houseNumber' => '2'],
        ]),
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    expect($response->json('data.addresses.items'))->toHaveCount(2);
    expect($response->json('data.addresses.within'))->toBeTrue();
    expect($response->json('data.all.items'))->toHaveCount(2);
});

// ============================
// VALIDATION
// ============================

test('request without any valid field returns 422', function () {
    $response = $this->postJson(route('api.locationserver.check'), [], [
        'Authorization' => 'Bearer '.$this->access_token,
    ]);

    $response->assertStatus(422);
});

// ============================
// COMBINED
// ============================

test('polygon and line together aggregate into all.items without duplicates', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'name' => 'DeGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-10,-10],[10,-10],[10,10],[-10,10],[-10,-10]]]]}',
    ]);

    $polygon = ['type' => 'Polygon', 'coordinates' => [[[-0.5, -0.5], [0.5, -0.5], [0.5, 0.5], [-0.5, 0.5], [-0.5, -0.5]]]];
    $line = json_encode(['type' => 'LineString', 'coordinates' => [[-1, 0], [1, 0]]]);

    $response = $this->postJson(route('api.locationserver.check'), [
        'polygons' => json_encode([$polygon]),
        'line' => $line,
    ], ['Authorization' => 'Bearer '.$this->access_token]);

    $response->assertStatus(200);

    // GM001 appears in both polygon and line intersection, but all.items should deduplicate
    expect($response->json('data.all.items'))->toHaveCount(1);
    expect($response->json('data.polygons.items'))->toHaveCount(1);
    expect($response->json('data.line.items'))->toHaveCount(1);
    expect($response->json('data.all.object'))->toHaveKey('GM001');
});
