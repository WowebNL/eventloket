<?php

declare(strict_types=1);

/**
 * Backfill-command: zet de OpenForms-submission die voor oude zaken nog in
 * de Objects API staat (record.data.data) om naar een form_state_snapshot.
 * De secties worden plat geslagen tot {key: value}; losse top-level velden
 * behouden hun key. Idempotent: zaken die al een snapshot hebben blijven met
 * rust, en --dry-run slaat niets op.
 */

use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['openzaak.objectsapi.url' => 'https://objects.test/']);
});

function fakeObjectsRecord(): void
{
    Http::fake([
        '*objects/*' => Http::response([
            'record' => ['data' => ['data' => [
                // Twee stap-secties (associatieve maps) ...
                'contactgegevens' => [
                    'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest',
                    'soortEvenement' => 'festival',
                ],
                'tijden' => [
                    'EvenementStart' => '2026-06-01',
                ],
                // ... en één los top-level veld.
                'losVeld' => 'waarde',
            ]]],
        ], 200),
    ]);
}

function oudeZaak(array $overrides = []): Zaak
{
    return Zaak::factory()->create(array_merge([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'data_object_url' => 'https://objects.test/api/v2/objects/abc-uuid-123',
        'form_state_snapshot' => null,
    ], $overrides));
}

test('zet de Objects-submission plat om naar form_state_snapshot.values', function () {
    fakeObjectsRecord();
    $zaak = oudeZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    $zaak->refresh();

    expect($zaak->form_state_snapshot)->toHaveKey('values');
    expect($zaak->form_state_snapshot['values'])->toMatchArray([
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest',
        'soortEvenement' => 'festival',
        'EvenementStart' => '2026-06-01',
        'losVeld' => 'waarde',
    ]);
});

test('--dry-run slaat niets op', function () {
    fakeObjectsRecord();
    $zaak = oudeZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id, '--dry-run' => true])
        ->assertSuccessful();

    $zaak->refresh();
    expect($zaak->form_state_snapshot)->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'objects/abc-uuid-123'));
});

test('zaak met bestaande snapshot wordt overgeslagen (idempotent)', function () {
    $zaak = oudeZaak(['form_state_snapshot' => ['values' => ['al' => 'gevuld']]]);

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    // Geen Objects-call gedaan en de bestaande snapshot ongemoeid gelaten.
    Http::assertNothingSent();
    $zaak->refresh();
    expect($zaak->form_state_snapshot['values'])->toBe(['al' => 'gevuld']);
});

test('zaak zonder data_object_url valt buiten de selectie', function () {
    $zaak = oudeZaak(['data_object_url' => null]);

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    Http::assertNothingSent();
    $zaak->refresh();
    expect($zaak->form_state_snapshot)->toBeNull();
});
