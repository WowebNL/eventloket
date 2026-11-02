<?php

declare(strict_types=1);

/**
 * A zaaksysteem may name its eigenschappen differently than the logical keys
 * this application uses, and the koppeling holds that translation. These tests
 * pin that a value changed in the zaaksysteem is read back onto the logical key
 * (and so becomes visible again), while a catalogus without a translation keeps
 * behaving exactly as before.
 */

use App\Actions\UpdateZaakReferenceData;
use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'identificatie' => 'EVT-1',
    ]);
});

/**
 * @param  array<string, string>  $eigenschappen  naam => waarde as the zaaksysteem returns them
 */
function fakeZaakWithEigenschappen(array $eigenschappen): string
{
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1';

    $expanded = [];
    foreach ($eigenschappen as $naam => $waarde) {
        $expanded[] = [
            'uuid' => 'eig-'.Str::slug($naam),
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen/'.Str::slug($naam),
            'zaak' => $zaakUrl,
            'eigenschap' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/'.Str::slug($naam),
            'naam' => $naam,
            'waarde' => $waarde,
        ];
    }

    ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'status' => [
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen/1',
                '_expand' => [
                    'statustype' => [
                        'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/2',
                        'omschrijving' => 'In behandeling',
                    ],
                ],
            ],
            'eigenschappen' => $expanded,
        ],
    ]);

    return $zaakUrl;
}

function mapEigenschappen(Municipality $municipality, array $eigenschapMap): void
{
    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
        'eigenschap_map' => $eigenschapMap,
    ]));
}

test('an eigenschap changed in the zaaksysteem under a translated naam is read back onto its logical key', function () {
    $zaakUrl = fakeZaakWithEigenschappen([
        '1.risico klasse' => 'C',
        '2.naam evenement' => 'Hernoemd evenement',
    ]);

    mapEigenschappen($this->municipality, [
        'risico_classificatie' => '1.risico klasse',
        'naam_evenement' => '2.naam evenement',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    expect($zaak->reference_data->risico_classificatie)->toBe('A')
        ->and($zaak->reference_data->naam_evenement)->toBe('Test event');

    UpdateZaakReferenceData::handle($zaak);

    $referenceData = $zaak->refresh()->reference_data;

    expect($referenceData->risico_classificatie)->toBe('C')
        ->and($referenceData->naam_evenement)->toBe('Hernoemd evenement')
        // The status still rides along on the same update.
        ->and($referenceData->status_name)->toBe('In behandeling');
});

test('a catalogus that names its eigenschappen after the logical keys is unaffected', function () {
    $zaakUrl = fakeZaakWithEigenschappen([
        'risico_classificatie' => 'B',
        'naam_evenement' => 'Onvertaald evenement',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    UpdateZaakReferenceData::handle($zaak);

    $referenceData = $zaak->refresh()->reference_data;

    expect($referenceData->risico_classificatie)->toBe('B')
        ->and($referenceData->naam_evenement)->toBe('Onvertaald evenement');
});

test('a translated eigenschap outranks a stray one carrying the logical key as its naam', function () {
    $zaakUrl = fakeZaakWithEigenschappen([
        'risico_classificatie' => 'A',
        '1.risico klasse' => 'C',
    ]);

    mapEigenschappen($this->municipality, ['risico_classificatie' => '1.risico klasse']);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    UpdateZaakReferenceData::handle($zaak);

    expect($zaak->refresh()->reference_data->risico_classificatie)->toBe('C');
});

test('an eigenschap the koppeling does not translate keeps being ignored', function () {
    $zaakUrl = fakeZaakWithEigenschappen(['3.onbekende eigenschap' => 'waarde']);

    mapEigenschappen($this->municipality, ['risico_classificatie' => '1.risico klasse']);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    UpdateZaakReferenceData::handle($zaak);

    // Nothing to translate it onto, so the stored value simply stays put.
    expect($zaak->refresh()->reference_data->risico_classificatie)->toBe('A');
});

/*
 * Date and date-time eigenschappen travel in the compact ZGW wire formats
 * (YYYYMMDD for a `datum`, YYYYMMDDHHMMSS for a `datum_tijd`). The reference
 * data stores ISO 8601 and is queried as text, so a value read back from the
 * zaaksysteem has to be turned back into ISO 8601 before it is merged.
 */
test('date-time eigenschappen read back in the ZGW wire format are stored as ISO 8601', function () {
    $zaakUrl = fakeZaakWithEigenschappen([
        'start_evenement' => '20261003140000',
        'eind_evenement' => '20261004230000',
        'start_opbouw' => '20261002080000',
        'eind_opbouw' => '20261003120000',
        'start_afbouw' => '20261005000000',
        'eind_afbouw' => '20261205173000',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    UpdateZaakReferenceData::handle($zaak);

    $stored = json_decode($zaak->refresh()->getRawOriginal('reference_data'), true);

    expect($stored['start_evenement'])->toBe('2026-10-03T14:00:00+02:00')
        ->and($stored['eind_evenement'])->toBe('2026-10-04T23:00:00+02:00')
        ->and($stored['start_opbouw'])->toBe('2026-10-02T08:00:00+02:00')
        ->and($stored['eind_opbouw'])->toBe('2026-10-03T12:00:00+02:00')
        ->and($stored['start_afbouw'])->toBe('2026-10-05T00:00:00+02:00')
        // Wall-clock time in Europe/Amsterdam, so winter time gets its own offset.
        ->and($stored['eind_afbouw'])->toBe('2026-12-05T17:30:00+01:00');
});

test('a date eigenschap read back in the ZGW wire format keeps the stored time of that day', function () {
    $zaakUrl = fakeZaakWithEigenschappen([
        'start_evenement' => '20261003',
        'eind_evenement' => '20261006',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
        'reference_data' => new ZaakReferenceData(
            registratiedatum: '2026-09-01T09:00:00+02:00',
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            start_evenement: '2026-10-03T14:00:00+02:00',
            eind_evenement: '2026-10-04T23:00:00+02:00',
        ),
    ]);

    UpdateZaakReferenceData::handle($zaak);

    $stored = json_decode($zaak->refresh()->getRawOriginal('reference_data'), true);

    // Same day as stored: the date carries no time, so the stored time stays.
    expect($stored['start_evenement'])->toBe('2026-10-03T14:00:00+02:00')
        // Another day: the date wins, at the start of that day.
        ->and($stored['eind_evenement'])->toBe('2026-10-06T00:00:00+02:00');
});

test('date-time eigenschappen that are already ISO 8601 are stored unchanged', function () {
    $zaakUrl = fakeZaakWithEigenschappen([
        'start_evenement' => '2026-10-03T14:00:00+02:00',
        'eind_evenement' => '2026-10-04T23:00:00+02:00',
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    UpdateZaakReferenceData::handle($zaak);

    $stored = json_decode($zaak->refresh()->getRawOriginal('reference_data'), true);

    expect($stored['start_evenement'])->toBe('2026-10-03T14:00:00+02:00')
        ->and($stored['eind_evenement'])->toBe('2026-10-04T23:00:00+02:00');
});

test('a date-time eigenschap under a translated naam is stored as ISO 8601 too', function () {
    $zaakUrl = fakeZaakWithEigenschappen(['4.start' => '20261003140000']);

    mapEigenschappen($this->municipality, ['start_evenement' => '4.start']);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    UpdateZaakReferenceData::handle($zaak);

    expect(json_decode($zaak->refresh()->getRawOriginal('reference_data'), true)['start_evenement'])
        ->toBe('2026-10-03T14:00:00+02:00');
});

test('a text eigenschap that happens to look like a wire date is left alone', function () {
    $zaakUrl = fakeZaakWithEigenschappen(['intern_zaaknummer' => '20261003140000']);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    UpdateZaakReferenceData::handle($zaak);

    expect($zaak->refresh()->reference_data->intern_zaaknummer)->toBe('20261003140000');
});
