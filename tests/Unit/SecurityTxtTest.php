<?php

use Illuminate\Support\Carbon;

// public/.well-known/security.txt is a static file served by the web server
// before any request reaches Laravel. These tests read the file itself and need
// no database, so they live in the Unit suite (no RefreshDatabase).

/**
 * Parse the non-comment "Field: value" lines of security.txt.
 *
 * @return array<int, array{0: string, 1: string}>
 */
function securityTxtFields(): array
{
    $path = public_path('.well-known/security.txt');

    expect(is_file($path))->toBeTrue('public/.well-known/security.txt is missing');

    $fields = [];

    foreach (preg_split('/\r?\n/', (string) file_get_contents($path)) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        expect($line)->toMatch('/^[A-Za-z-]+: .+$/', "Malformed security.txt line: {$line}");

        [$name, $value] = explode(': ', $line, 2);
        $fields[] = [strtolower($name), trim($value)];
    }

    return $fields;
}

/** @return array<int, string> */
function securityTxtValues(string $field): array
{
    return array_values(array_map(
        fn (array $pair): string => $pair[1],
        array_filter(securityTxtFields(), fn (array $pair): bool => $pair[0] === strtolower($field)),
    ));
}

it('has at least one contact that is an https or mailto uri', function () {
    $contacts = securityTxtValues('Contact');

    expect($contacts)->not->toBeEmpty();

    foreach ($contacts as $contact) {
        expect($contact)->toMatch('/^(https:\/\/\S+|mailto:[^\s@<>]+@[^\s@<>]+)$/');
    }
});

it('has exactly one valid expires field that is at most a year ahead', function () {
    $expires = securityTxtValues('Expires');

    expect($expires)->toHaveCount(1);
    expect($expires[0])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/');
    expect(Carbon::parse($expires[0])->lessThanOrEqualTo(now()->addYear()->addDay()))->toBeTrue(
        'RFC 9116 recommends an Expires value less than a year in the future.'
    );
});

it('does not expire within the next 30 days', function () {
    $expires = Carbon::parse(securityTxtValues('Expires')[0]);

    expect($expires->greaterThan(now()->addDays(30)))->toBeTrue(
        "security.txt expires on {$expires->toDateString()}. Move the Expires field forward (at most one year)."
    );
});

it('declares the preferred languages', function () {
    expect(securityTxtValues('Preferred-Languages'))->toBe(['nl, en']);
});
