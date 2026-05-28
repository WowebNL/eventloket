<?php

declare(strict_types=1);

use App\EventForm\Services\EventsCheckService;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create(['brk_identification' => 'GM0882']);
    $this->zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->service = new EventsCheckService;
});

test('finds events that start within the date range', function () {
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-05-05',
            eind_evenement: '2026-05-06',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Bevrijdingsfestival',
        ),
    ]);

    $result = $this->service->check('2026-05-01', '2026-05-10', 'GM0882');

    expect($result)->toBe([
        'event_names' => 'Bevrijdingsfestival',
        'event_count' => 1,
    ]);
});

test('returns empty when no matches', function () {
    $result = $this->service->check('2030-01-01', '2030-01-10', 'GM0882');

    expect($result)->toBe([
        'event_names' => '',
        'event_count' => 0,
    ]);
});

test('filters by municipality', function () {
    $other = Municipality::factory()->create(['brk_identification' => 'GM0999']);
    $otherZt = Zaaktype::factory()->create(['municipality_id' => $other->id]);

    Zaak::factory()->create([
        'zaaktype_id' => $otherZt->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-05-05',
            eind_evenement: '2026-05-06',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Elders',
        ),
    ]);

    $result = $this->service->check('2026-05-01', '2026-05-10', 'GM0882');

    expect($result['event_count'])->toBe(0);
});
