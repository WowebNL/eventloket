<?php

use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create([
        'brk_identification' => 'GM0123',
    ]);

    $this->otherMunicipality = Municipality::factory()->create([
        'brk_identification' => 'GM0456',
    ]);

    // Create zaaktype for the municipality
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->otherZaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->otherMunicipality->id,
    ]);
});

test('can check events within date range and municipality', function () {
    // Create zaken with events within the date range for the target municipality
    $zaak1 = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-15',
            eind_evenement: '2025-01-17',
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'Test Event 1'
        ),
    ]);

    $zaak2 = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-20',
            eind_evenement: '2025-01-22',
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'Test Event 2'
        ),
    ]);

    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10',
        'end_date' => '2025-01-25',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'event_names' => 'Test Event 1, Test Event 2',
            'event_count' => 2,
        ]);
});

test('returns events that start within date range', function () {
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-15',
            eind_evenement: '2025-02-05', // Ends after range
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'Event Starting In Range'
        ),
    ]);

    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10',
        'end_date' => '2025-01-25',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'event_names' => 'Event Starting In Range',
            'event_count' => 1,
        ]);
});

test('returns events that end within date range', function () {
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-05', // Starts before range
            eind_evenement: '2025-01-15',
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'Event Ending In Range'
        ),
    ]);

    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10',
        'end_date' => '2025-01-25',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'event_names' => 'Event Ending In Range',
            'event_count' => 1,
        ]);
});

test('excludes events from other municipalities', function () {
    // Event in target municipality
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-15',
            eind_evenement: '2025-01-17',
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'Target Municipality Event'
        ),
    ]);

    // Event in different municipality
    Zaak::factory()->create([
        'zaaktype_id' => $this->otherZaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-15',
            eind_evenement: '2025-01-17',
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'Other Municipality Event'
        ),
    ]);

    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10',
        'end_date' => '2025-01-25',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'event_names' => 'Target Municipality Event',
            'event_count' => 1,
        ]);
});

test('excludes events outside date range', function () {
    // Event before date range
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-01',
            eind_evenement: '2025-01-05',
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'Event Before Range'
        ),
    ]);

    // Event after date range
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-02-01',
            eind_evenement: '2025-02-05',
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'Event After Range'
        ),
    ]);

    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10',
        'end_date' => '2025-01-25',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'event_names' => '',
            'event_count' => 0,
        ]);
});

test('returns empty result when no events found', function () {
    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10',
        'end_date' => '2025-01-25',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'event_names' => '',
            'event_count' => 0,
        ]);
});

test('limits results to 10 events', function () {
    // Create 15 events
    for ($i = 1; $i <= 15; $i++) {
        Zaak::factory()->create([
            'zaaktype_id' => $this->zaaktype->id,
            'reference_data' => new ZaakReferenceData(
                start_evenement: '2025-01-15',
                eind_evenement: '2025-01-17',
                registratiedatum: now(),
                status_name: 'Ontvangen',
                naam_evenement: "Event {$i}"
            ),
        ]);
    }

    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10',
        'end_date' => '2025-01-25',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(200);

    $data = $response->json();
    expect($data['event_count'])->toBe(10);

    // Count comma-separated event names to verify limit
    $eventNames = $data['event_names'];
    $eventNamesArray = array_filter(explode(', ', $eventNames));
    expect($eventNamesArray)->toHaveCount(10);
});

test('requires valid request data', function () {
    $response = $this->withoutMiddleware()->postJson('/api/events/check', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'end_date', 'municipality']);
});

test('validates municipality exists', function () {
    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10',
        'end_date' => '2025-01-25',
        'municipality' => 'INVALID',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['municipality']);
});

test('handles datetime format in start_date and end_date', function () {
    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2025-01-15',
            eind_evenement: '2025-01-17',
            registratiedatum: now(),
            status_name: 'Ontvangen',
            naam_evenement: 'DateTime Test Event'
        ),
    ]);

    $response = $this->withoutMiddleware()->postJson('/api/events/check', [
        'start_date' => '2025-01-10 09:00:00',
        'end_date' => '2025-01-25 18:00:00',
        'municipality' => 'GM0123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'event_names' => 'DateTime Test Event',
            'event_count' => 1,
        ]);
});
