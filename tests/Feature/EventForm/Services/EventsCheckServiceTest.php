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

test('finds an event that spans the whole date range', function () {
    // Een meerdaags evenement dat begint vóór en eindigt ná het opgegeven
    // venster overlapt wel degelijk, maar viel voorheen buiten de boot omdat
    // geen van beide uiteinden binnen het venster lag.
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-05-01T10:00:00+02:00',
            eind_evenement: '2026-05-20T18:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Meerdaagse kermis',
        ),
    ]);

    $result = $this->service->check('2026-05-05', '2026-05-06', 'GM0882');

    expect($result)->toBe([
        'event_names' => 'Meerdaagse kermis',
        'event_count' => 1,
    ]);
});

test('ignores events that end before or start after the date range', function () {
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-04-01T10:00:00+02:00',
            eind_evenement: '2026-04-02T18:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Ervoor',
        ),
    ]);
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-06-01T10:00:00+02:00',
            eind_evenement: '2026-06-02T18:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Erna',
        ),
    ]);

    expect($this->service->check('2026-05-01', '2026-05-10', 'GM0882')['event_count'])->toBe(0);
});

test('een zaak met een niet-ISO datum laat de query niet klappen', function () {
    // Oudere zaken kunnen een Nederlandse datumnotatie bevatten. Die mag geen
    // databasefout opleveren; de zaak valt simpelweg buiten de vergelijking.
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '5 mei 2026 10:00',
            eind_evenement: '6 mei 2026 18:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Oud formaat',
        ),
    ]);

    expect($this->service->check('2026-05-01', '2026-05-10', 'GM0882')['event_count'])->toBe(0);
});

test('telt alle overlappende evenementen ook als er meer dan tien namen zijn', function () {
    foreach (range(1, 12) as $nummer) {
        Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'reference_data' => new ZaakReferenceData(
                start_evenement: '2026-05-05T10:00:00+02:00',
                eind_evenement: '2026-05-05T18:00:00+02:00',
                registratiedatum: now()->toIso8601String(),
                status_name: 'Ontvangen',
                statustype_url: '',
                naam_evenement: "Evenement {$nummer}",
            ),
        ]);
    }

    $result = $this->service->check('2026-05-01', '2026-05-10', 'GM0882');

    expect($result['event_count'])->toBe(12);
    expect(explode(', ', $result['event_names']))->toHaveCount(10);
});

test('een omgekeerd opgegeven venster levert hetzelfde resultaat op', function () {
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-05-05T10:00:00+02:00',
            eind_evenement: '2026-05-06T18:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ontvangen',
            statustype_url: '',
            naam_evenement: 'Bevrijdingsfestival',
        ),
    ]);

    expect($this->service->check('2026-05-10', '2026-05-01', 'GM0882')['event_count'])->toBe(1);
});

test('een leeg of onleesbaar venster levert geen resultaten op', function () {
    expect($this->service->check('', '2026-05-10', 'GM0882'))->toBe([
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
