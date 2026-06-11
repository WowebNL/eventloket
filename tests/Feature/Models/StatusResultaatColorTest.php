<?php

use App\Models\StatusResultaatColor;

test('colorFor returns the color for a status without a resultaat', function () {
    StatusResultaatColor::factory()->create([
        'status_name' => 'TestOntvangen',
        'resultaat' => null,
        'color' => '#3B82F6',
    ]);

    expect(StatusResultaatColor::colorFor('TestOntvangen'))->toBe('#3B82F6');
    expect(StatusResultaatColor::colorFor('TestOntvangen', null))->toBe('#3B82F6');
});

test('colorFor returns the color for a status with a specific resultaat', function () {
    StatusResultaatColor::factory()->create([
        'status_name' => 'TestAfgerond',
        'resultaat' => null,
        'color' => '#22C55E',
    ]);
    StatusResultaatColor::factory()->create([
        'status_name' => 'TestAfgerond',
        'resultaat' => 'Geweigerd',
        'color' => '#EF4444',
    ]);

    expect(StatusResultaatColor::colorFor('TestAfgerond', 'Geweigerd'))->toBe('#EF4444');
    expect(StatusResultaatColor::colorFor('TestAfgerond'))->toBe('#22C55E');
});

test('colorFor returns null when no matching combination is configured', function () {
    expect(StatusResultaatColor::colorFor('Onbekend', 'Iets'))->toBeNull();
});

test('colorFor reflects updates after the color is changed', function () {
    $color = StatusResultaatColor::factory()->create([
        'status_name' => 'TestOntvangen',
        'resultaat' => null,
        'color' => '#3B82F6',
    ]);

    expect(StatusResultaatColor::colorFor('TestOntvangen'))->toBe('#3B82F6');

    $color->update(['color' => '#000000']);

    expect(StatusResultaatColor::colorFor('TestOntvangen'))->toBe('#000000');
});

test('colorFor reflects deletion of a color', function () {
    $color = StatusResultaatColor::factory()->create([
        'status_name' => 'TestOntvangen',
        'resultaat' => null,
        'color' => '#3B82F6',
    ]);

    expect(StatusResultaatColor::colorFor('TestOntvangen'))->toBe('#3B82F6');

    $color->delete();

    expect(StatusResultaatColor::colorFor('TestOntvangen'))->toBeNull();
});
