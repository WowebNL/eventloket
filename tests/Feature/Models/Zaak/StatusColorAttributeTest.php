<?php

use App\Models\StatusResultaatColor;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Tests\Fakes\ZgwHttpFake;

beforeEach(function () {
    $this->zaaktype = Zaaktype::factory()->create();

    // Remove the seeded default colors so tests fully control the configured combinations.
    StatusResultaatColor::query()->delete();
});

test('status_color resolves the configured color for the zaak status', function () {
    StatusResultaatColor::factory()->create([
        'status_name' => 'Ontvangen',
        'resultaat' => null,
        'color' => '#3B82F6',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    expect($zaak->status_color)->toBe('#3B82F6');
});

test('status_color resolves the configured color for an eindstatus with resultaat', function () {
    StatusResultaatColor::factory()->create([
        'status_name' => 'Afgerond',
        'resultaat' => 'Verleend',
        'color' => '#22C55E',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            now(),
            now()->addDay(),
            now(),
            'Afgerond',
            ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            'A',
            'Test locatie',
            'Test event',
            resultaat: 'Verleend',
        ),
    ]);

    expect($zaak->status_color)->toBe('#22C55E');
});

test('status_color is null when no matching color is configured', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            now(),
            now()->addDay(),
            now(),
            'OnbekendeStatus',
            ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            'A',
            'Test locatie',
            'Test event',
        ),
    ]);

    expect($zaak->status_color)->toBeNull();
});

test('toCalendarEvent sets the background color when a color is configured', function () {
    StatusResultaatColor::factory()->create([
        'status_name' => 'Ontvangen',
        'resultaat' => null,
        'color' => '#3B82F6',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
    ]);

    expect($zaak->toCalendarEvent()->getBackgroundColor())->toBe('#3B82F6');
});

test('toCalendarEvent does not set a background color when none is configured', function () {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            now(),
            now()->addDay(),
            now(),
            'OnbekendeStatus',
            ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            'A',
            'Test locatie',
            'Test event',
        ),
    ]);

    expect($zaak->toCalendarEvent()->getBackgroundColor())->toBeNull();
});
