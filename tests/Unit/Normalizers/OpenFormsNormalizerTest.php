<?php

use App\Normalizers\OpenFormsNormalizer;

test('normalizeSingleQuotes handles mixed quotes and apostrophes in names', function () {
    $input = "[{'naamVanDeLocatieGebouw': 'd'n oude klapschaats', 'adresVanHetGebouwWaarUwEvenementPlaatsvindt1': {'city': 'Cadier en Keer', 'postcode': '6267 ec', 'streetName': 'Kerkstraat', 'houseLetter': '', 'houseNumber': '125', 'houseNumberAddition': ''}}]";

    $normalized = OpenFormsNormalizer::normalizeSingleQuotes($input);

    expect(json_validate($normalized))->toBeTrue();

    $data = json_decode($normalized, true);
    expect($data[0]['naamVanDeLocatieGebouw'])->toBe("d'n oude klapschaats");
    expect($data[0]['adresVanHetGebouwWaarUwEvenementPlaatsvindt1']['postcode'])->toBe('6267 ec');
});

test('normalizeSingleQuotes produces valid JSON for nested coordinates', function () {
    $input = "[{'naamVanDeLocatieKaart': ''t schip', 'buitenLocatieVanHetEvenement': {'type': 'Polygon', 'coordinates': [[[ '5.842957', '50.896426'], ['5.834288', '50.888487'], ['5.861151', '50.883185'], ['5.863203', '50.89707'], ['5.842957', '50.896426']]]}}]";

    $normalized = OpenFormsNormalizer::normalizeSingleQuotes($input);

    expect(json_validate($normalized))->toBeTrue();

    $data = json_decode($normalized, true);
    expect($data[0]['naamVanDeLocatieKaart'])->toBe("'t schip");
    expect($data[0]['buitenLocatieVanHetEvenement']['type'])->toBe('Polygon');
});

test('normalizeGeoJson casts coordinate strings to floats', function () {
    $geoJson = json_encode([
        'type' => 'Polygon',
        'coordinates' => [[['5.842957', '50.896426']]],
    ]);

    $normalized = OpenFormsNormalizer::normalizeGeoJson($geoJson);

    $data = json_decode($normalized, true);
    expect($data['coordinates'][0][0][0])->toBe(5.842957);
    expect($data['coordinates'][0][0][1])->toBe(50.896426);
});
