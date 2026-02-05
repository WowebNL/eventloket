<?php

use App\Filament\Shared\Imports\ZaakImporter;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Carbon\Carbon;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

covers(ZaakImporter::class);

/**
 * Helper function to invoke protected/private methods for testing
 */
function invokeZaakImporterMethod($methodName, array $parameters = [])
{
    $reflection = new \ReflectionClass(ZaakImporter::class);
    $method = $reflection->getMethod($methodName);

    // For static methods, pass null as the first argument
    return $method->invoke(null, ...$parameters);
}

/**
 * Helper function to create a ZaakImporter instance with data for testing
 */
function createImporterWithData(array $data = []): ZaakImporter
{
    // Create a mock Import model
    $import = \Mockery::mock(\Filament\Actions\Imports\Models\Import::class);

    $importer = new ZaakImporter($import, [], []);

    // Set the protected $data property
    $reflection = new \ReflectionClass($importer);
    $property = $reflection->getProperty('data');
    $property->setValue($importer, $data);

    return $importer;
}

/**
 * Helper function to get the record from importer (it's protected)
 */
function getImporterRecord(ZaakImporter $importer): ?Zaak
{
    $reflection = new \ReflectionClass($importer);
    $property = $reflection->getProperty('record');

    return $property->getValue($importer);
}

test('parseDate handles all supported date formats correctly', function () {
    $testCases = [
        ['19/02/2026', '2026-02-19'], // d/m/Y
        ['19-02-2026', '2026-02-19'], // d-m-Y
        ['19/02/26', '2026-02-19'],   // d/m/y
        ['19-02-26', '2026-02-19'],   // d-m-y
        ['2026-02-19', '2026-02-19'], // Y-m-d
        ['19/2/2026', '2026-02-19'],  // d/n/Y (single digit month)
        ['19-2-2026', '2026-02-19'],  // d-n-Y
        ['19/2/26', '2026-02-19'],    // d/n/y
        ['19-2-26', '2026-02-19'],    // d-n-y
    ];

    foreach ($testCases as [$input, $expected]) {
        $parsed = invokeZaakImporterMethod('parseDate', [$input]);

        expect($parsed)->toBe($expected);
    }
});

test('parseDate handles datetime formats with hours and minutes', function () {
    $testCases = [
        ['07/06/2026 18:30', '2026-06-07T18:30:00+02:00'], // d/m/Y H:i
        ['07-06-2026 18:30', '2026-06-07T18:30:00+02:00'], // d-m-Y H:i
        ['2026-06-07 18:30', '2026-06-07T18:30:00+02:00'], // Y-m-d H:i
        ['07/6/2026 09:15', '2026-06-07T09:15:00+02:00'],  // d/n/Y H:i
    ];

    foreach ($testCases as [$input, $expected]) {
        $parsed = invokeZaakImporterMethod('parseDate', [$input]);

        expect($parsed)->toBe($expected);
    }
});

test('parseDate handles datetime formats with hours minutes and seconds', function () {
    $testCases = [
        ['07/06/2026 18:30:45', '2026-06-07T18:30:45+02:00'], // d/m/Y H:i:s
        ['07-06-2026 18:30:45', '2026-06-07T18:30:45+02:00'], // d-m-Y H:i:s
        ['2026-06-07 18:30:45', '2026-06-07T18:30:45+02:00'], // Y-m-d H:i:s
    ];

    foreach ($testCases as [$input, $expected]) {
        $parsed = invokeZaakImporterMethod('parseDate', [$input]);

        expect($parsed)->toBe($expected);
    }
});

test('parseDate uses Amsterdam timezone for datetime formats', function () {
    $input = '15/06/2026 14:30';
    $parsed = invokeZaakImporterMethod('parseDate', [$input]);

    // Parse the result to check timezone
    $carbon = Carbon::parse($parsed);

    expect($carbon->timezone->getName())->toBe('+02:00')
        ->and($carbon->format('Y-m-d\TH:i:sP'))->toBe('2026-06-15T14:30:00+02:00');
});

test('parseDate returns date-only format when no time is provided', function () {
    $input = '19/02/2026';
    $parsed = invokeZaakImporterMethod('parseDate', [$input]);

    expect($parsed)->toBe('2026-02-19')
        ->and($parsed)->not->toContain('T');
});

test('parseDate returns null for empty string', function () {
    $parsed = invokeZaakImporterMethod('parseDate', ['']);

    expect($parsed)->toBeNull();
});

test('parseDate returns null for invalid date', function () {
    $parsed = invokeZaakImporterMethod('parseDate', ['invalid-date']);

    expect($parsed)->toBeNull();
});

// fillRecord tests
test('fillRecord creates zaak with correct zaaktype_id', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0001']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Melding',
    ]);

    $data = [
        'municipality_code' => 'GM0001',
        'type' => 'melding',
        'submission_date' => '19/02/2026',
        'start_date' => '20/02/2026',
        'end_date' => '21/02/2026',
        'status' => 'Ontvangen',
        'event_name' => 'Test Event',
        'organisation_name' => 'Test Org',
        'expected_visitors' => '100',
        'event_type' => 'Concert',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    expect($record)
        ->toBeInstanceOf(Zaak::class)
        ->and($record->zaaktype_id)->toBe($zaaktype->id);
});

test('fillRecord creates zaak with reference_data containing all required fields', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0002']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Vergunning',
    ]);

    $data = [
        'municipality_code' => 'GM0002',
        'type' => 'vergunning',
        'submission_date' => '19/02/2026',
        'start_date' => '20/02/2026',
        'end_date' => '21/02/2026',
        'status' => 'Verleend',
        'event_name' => 'Concert Night',
        'organisation_name' => 'Music Org',
        'expected_visitors' => '500',
        'event_type' => 'Music Festival',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    $referenceData = $record->reference_data;
    expect($referenceData->naam_evenement)->toBe('Concert Night')
        ->and($referenceData->organisator)->toBe('Music Org')
        ->and($referenceData->aanwezigen)->toBe('500')
        ->and($referenceData->types_evenement)->toBe(['Music Festival'])
        ->and($referenceData->status_name)->toBe('Verleend');
});

test('fillRecord parses date-only formats correctly in reference_data', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0003']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Vooraankondiging',
    ]);

    $data = [
        'municipality_code' => 'GM0003',
        'type' => 'vooraankondiging',
        'submission_date' => '19/02/2026',
        'start_date' => '25/02/2026',
        'end_date' => '26/02/2026',
        'status' => 'Openbaar',
        'event_name' => 'Party',
        'organisation_name' => 'Party Org',
        'expected_visitors' => '200',
        'event_type' => 'Celebration',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    $referenceData = $record->reference_data;
    expect($referenceData->registratiedatum)->toBe('2026-02-19')
        ->and($referenceData->start_evenement)->toBe('2026-02-25')
        ->and($referenceData->eind_evenement)->toBe('2026-02-26');
});

test('fillRecord parses datetime formats correctly in reference_data', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0009']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Vooraankondiging',
    ]);

    $data = [
        'municipality_code' => 'GM0009',
        'type' => 'vooraankondiging',
        'submission_date' => '19/02/2026 10:30',
        'start_date' => '25/02/2026 18:00',
        'end_date' => '26/02/2026 23:30',
        'status' => 'Openbaar',
        'event_name' => 'Evening Party',
        'organisation_name' => 'Party Org',
        'expected_visitors' => '200',
        'event_type' => 'Celebration',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    $referenceData = $record->reference_data;
    expect($referenceData->registratiedatum)->toBe('2026-02-19T10:30:00+01:00')
        ->and($referenceData->start_evenement)->toBe('2026-02-25T18:00:00+01:00')
        ->and($referenceData->eind_evenement)->toBe('2026-02-26T23:30:00+01:00');
});

test('fillRecord handles mixed date and datetime formats', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0010']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Melding',
    ]);

    $data = [
        'municipality_code' => 'GM0010',
        'type' => 'melding',
        'submission_date' => '19/02/2026',
        'start_date' => '25/02/2026 14:30',
        'end_date' => '26/02/2026',
        'status' => 'Ontvangen',
        'event_name' => 'Mixed Event',
        'organisation_name' => 'Test Org',
        'expected_visitors' => '150',
        'event_type' => 'Festival',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    $referenceData = $record->reference_data;
    expect($referenceData->registratiedatum)->toBe('2026-02-19')
        ->and($referenceData->start_evenement)->toBe('2026-02-25T14:30:00+01:00')
        ->and($referenceData->eind_evenement)->toBe('2026-02-26');
});

test('fillRecord stores imported_data', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0004']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Melding',
    ]);

    $data = [
        'municipality_code' => 'GM0004',
        'type' => 'melding',
        'submission_date' => '19/02/2026',
        'start_date' => '20/02/2026',
        'end_date' => '21/02/2026',
        'status' => 'Ontvangen',
        'event_name' => 'Test Event',
        'organisation_name' => 'Test Org',
        'expected_visitors' => '100',
        'event_type' => 'Concert',
        'extra_field' => 'Extra data',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    expect($record->imported_data)->toBe($data);
});

test('fillRecord throws exception when municipality not found', function () {
    // Arrange
    $data = [
        'municipality_code' => 'GM9999',
        'type' => 'melding',
        'submission_date' => '19/02/2026',
        'start_date' => '20/02/2026',
        'end_date' => '21/02/2026',
        'status' => 'Ontvangen',
        'event_name' => 'Test Event',
        'organisation_name' => 'Test Org',
        'expected_visitors' => '100',
        'event_type' => 'Concert',
    ];

    $importer = createImporterWithData($data);

    // Act & Assert
    expect(function () use ($importer) {
        $importer->fillRecord();
    })->toThrow(RowImportFailedException::class);
});

test('fillRecord throws exception when zaaktype not found', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0005']);

    $data = [
        'municipality_code' => 'GM0005',
        'type' => 'nonexistent_type',
        'submission_date' => '19/02/2026',
        'start_date' => '20/02/2026',
        'end_date' => '21/02/2026',
        'status' => 'Ontvangen',
        'event_name' => 'Test Event',
        'organisation_name' => 'Test Org',
        'expected_visitors' => '100',
        'event_type' => 'Concert',
    ];

    $importer = createImporterWithData($data);

    // Act & Assert
    expect(function () use ($importer) {
        $importer->fillRecord();
    })->toThrow(RowImportFailedException::class);
});

test('fillRecord finds zaaktype by case-insensitive partial match', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0006']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Melding evenement',
    ]);

    $data = [
        'municipality_code' => 'GM0006',
        'type' => 'melding',
        'submission_date' => '19/02/2026',
        'start_date' => '20/02/2026',
        'end_date' => '21/02/2026',
        'status' => 'Ontvangen',
        'event_name' => 'Test Event',
        'organisation_name' => 'Test Org',
        'expected_visitors' => '100',
        'event_type' => 'Concert',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    expect($record->zaaktype_id)->toBe($zaaktype->id);
});

test('fillRecord sets statustype_url to empty string', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0007']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Melding',
    ]);

    $data = [
        'municipality_code' => 'GM0007',
        'type' => 'melding',
        'submission_date' => '19/02/2026',
        'start_date' => '20/02/2026',
        'end_date' => '21/02/2026',
        'status' => 'Ontvangen',
        'event_name' => 'Test Event',
        'organisation_name' => 'Test Org',
        'expected_visitors' => '100',
        'event_type' => 'Concert',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    expect($record->reference_data->statustype_url)->toBe('');
});

test('fillRecord sets optional fields to null', function () {
    // Arrange
    $municipality = Municipality::factory()->create(['brk_identification' => 'GM0008']);
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Vergunning',
    ]);

    $data = [
        'municipality_code' => 'GM0008',
        'type' => 'vergunning',
        'submission_date' => '19/02/2026',
        'start_date' => '20/02/2026',
        'end_date' => '21/02/2026',
        'status' => 'Verleend',
        'event_name' => 'Test Event',
        'organisation_name' => 'Test Org',
        'expected_visitors' => '100',
        'event_type' => 'Concert',
    ];

    $importer = createImporterWithData($data);

    // Act
    $importer->fillRecord();

    // Assert
    $record = getImporterRecord($importer);
    $referenceData = $record->reference_data;
    expect($referenceData->risico_classificatie)->toBeNull()
        ->and($referenceData->naam_locatie_eveneme)->toBeNull()
        ->and($referenceData->resultaat)->toBeNull();
});
