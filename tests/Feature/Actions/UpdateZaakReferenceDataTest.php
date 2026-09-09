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
