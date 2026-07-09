<?php

declare(strict_types=1);

use App\EventForm\Support\SafeDateTime;
use Carbon\CarbonInterface;

test('parse returns a Carbon instance for a clean value', function (string $value) {
    expect(SafeDateTime::parse($value))
        ->toBeInstanceOf(CarbonInterface::class);
})->with([
    'datetime' => '2026-08-22T13:00',
    'datetime with seconds' => '2026-08-22T13:00:00',
    'date only' => '2026-08-22',
    'space separator' => '2026-08-22 13:00',
]);

test('parse returns null for a malformed or blank value', function (mixed $value) {
    expect(SafeDateTime::parse($value))->toBeNull();
})->with([
    'five digit year' => '20256-09-20T16:00',
    'six digit year' => '202026-08-22T13:00',
    'empty string' => '',
    'null' => null,
    'not a date' => 'geen datum',
    'integer' => 2026,
]);

test('sanitizeState nulls out datetime strings with an over-long year, recursively', function () {
    $state = [
        'EvenementStart' => '202026-08-22T13:00',
        'EvenementEind' => '2026-08-22T18:00',
        'naam' => 'Een gewoon evenement',
        'repeater' => [
            ['startTijdstipVoorwerp' => '20256-09-20T16:00', 'voorwerp' => 'kraam'],
        ],
    ];

    expect(SafeDateTime::sanitizeState($state))->toBe([
        'EvenementStart' => null,
        'EvenementEind' => '2026-08-22T18:00',
        'naam' => 'Een gewoon evenement',
        'repeater' => [
            ['startTijdstipVoorwerp' => null, 'voorwerp' => 'kraam'],
        ],
    ]);
});

test('sanitizeState leaves valid dates and non-date values untouched', function () {
    $state = [
        'EvenementStart' => '2026-08-22T13:00',
        'uuid' => '00f09aee-fedd-44d6-b82c-3e3754d67b7a',
        'aantal' => 1500,
        'geometry' => [5.6910, 50.8514],
    ];

    expect(SafeDateTime::sanitizeState($state))->toBe($state);
});
