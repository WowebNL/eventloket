<?php

declare(strict_types=1);

use App\Enums\BlueprintFindingType;
use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Services\Zgw\ZaaktypeBlueprintHealth;
use App\Services\Zgw\ZgwConnectionResolver;
use App\ValueObjects\ZGW\BlueprintFinding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

/**
 * The bijlage-documenttype also types the documents an organiser uploads on a
 * zaak, and those uploads offer no choice. Leaving the slot empty keeps working
 * (a heuristic picks a type), but the koppelingbeheerder should be told.
 */
function fakeCompleteCatalogus(string $base): void
{
    Http::fake([
        "{$base}/catalogi/api/v1/zaaktypen*" => Http::response(ZgwHttpFake::envelope([
            ['identificatie' => 'EVT-1', 'omschrijving' => 'Evenementenvergunning', 'url' => "{$base}/catalogi/api/v1/zaaktypen/1"],
        ])),
        "{$base}/catalogi/api/v1/statustypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
            ['omschrijving' => 'Afgehandeld', 'volgnummer' => 2, 'isEindstatus' => true],
        ])),
        "{$base}/catalogi/api/v1/roltypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Aanvrager', 'omschrijvingGeneriek' => 'initiator'],
        ])),
        "{$base}/catalogi/api/v1/resultaattypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Ingetrokken', 'omschrijvingGeneriek' => 'Ingetrokken'],
        ])),
        "{$base}/catalogi/api/v1/zaaktype-informatieobjecttypen*" => Http::response(ZgwHttpFake::envelope([
            ['informatieobjecttype' => 'Bijlage'],
        ])),
        "{$base}/catalogi/api/v1/eigenschappen*" => Http::response(ZgwHttpFake::envelope([])),
    ]);
}

/**
 * @return list<BlueprintFinding>
 */
function documenttypeFindings(Municipality $municipality, ?MunicipalityZaaktypeMapping $mapping = null): array
{
    $connectionName = app(ZgwConnectionResolver::class)->forManagement($municipality);

    return app(ZaaktypeBlueprintHealth::class)->check($connectionName, 'EVT-1', $mapping);
}

beforeEach(function () {
    Cache::flush();
    $this->municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->create([
        'municipality_id' => $this->municipality->id,
        'allow_organiser_withdrawal' => true,
    ]);
    fakeCompleteCatalogus('https://gemeente.example.com');
});

test('a koppeling without a bijlage-documenttype is reported as not configured', function () {
    $mapping = MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
    ]);

    $findings = documenttypeFindings($this->municipality, $mapping);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->slot)->toBe('bijlage_informatieobjecttype')
        ->and($findings[0]->type)->toBe(BlueprintFindingType::NotConfigured)
        ->and($findings[0]->expected)->toBeNull();
});

test('a koppeling with a bijlage-documenttype produces no finding', function () {
    $mapping = MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
        'bijlage_informatieobjecttype' => 'Bijlage',
    ]);

    expect(documenttypeFindings($this->municipality, $mapping))->toBe([]);
});

test('a zaaktype without a koppeling row produces no not-configured finding', function () {
    // Main zaaktypen are checked without a mapping by design; they must not
    // raise a warning for a slot they cannot configure.
    expect(documenttypeFindings($this->municipality))->toBe([]);
});
