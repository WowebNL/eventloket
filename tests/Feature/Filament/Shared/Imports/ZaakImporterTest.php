<?php

use App\Filament\Shared\Imports\ZaakImporter;
use Carbon\Carbon;

covers(ZaakImporter::class);

/**
 * Helper function to invoke protected/private methods for testing
 */
function invokeZaakImporterMethod($methodName, array $parameters = [])
{
    $reflection = new \ReflectionClass(ZaakImporter::class);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    // For static methods, pass null as the first argument
    return $method->invokeArgs(null, $parameters);
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

        expect($parsed)
            ->toBeInstanceOf(Carbon::class)
            ->and($parsed->format('Y-m-d'))->toBe($expected);
    }
});

test('parseDate sets time to start of day', function () {
    $date = '19/02/2026';
    $parsed = invokeZaakImporterMethod('parseDate', [$date]);

    expect($parsed->format('H:i:s'))->toBe('00:00:00');
});
