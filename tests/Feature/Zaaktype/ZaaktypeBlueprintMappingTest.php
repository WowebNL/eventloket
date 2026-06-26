<?php

declare(strict_types=1);

/**
 * The per-municipality blueprint (Onderdeel 5) drives zaaktype resolution and
 * is seeded from the current name conventions by app:backfill-zaaktype-mappings.
 */

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;
use App\EventForm\Submit\ResolveZaaktype;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolve = new ResolveZaaktype(new DetermineAanvraagType);
});

test('ResolveZaaktype picks the zaaktype by the blueprint identificatie, ignoring the name prefix', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    // A zaaktype whose name does NOT match the "Evenementenvergunning" prefix,
    // so only the blueprint mapping can select it.
    $blueprintZaaktype = Zaaktype::factory()->create([
        'name' => 'Speciaal evenement gemeente Heerlen',
        'identificatie' => 'ZTC-VG-001',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);
    // The prefix-matching zaaktype that the heuristic would otherwise pick.
    Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'identificatie' => 'ZTC-VG-LEGACY',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $heerlen->id,
        'role' => ZaaktypeRole::Vergunning->value,
        'zaaktype_identificatie' => 'ZTC-VG-001',
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($blueprintZaaktype->id);
});

test('ResolveZaaktype falls back to the name prefix when the blueprint has no zaaktype_identificatie', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);
    $verwacht = Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'identificatie' => 'ZTC-VG-001',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    // A mapping row exists but without a zaaktype link -> heuristic applies.
    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $heerlen->id,
        'role' => ZaaktypeRole::Vergunning->value,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($verwacht->id);
});

test('forZaaktype matches by municipality and logical identificatie', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen']);
    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => 'ZTC-VG-001',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);
    $mapping = MunicipalityZaaktypeMapping::create([
        'municipality_id' => $heerlen->id,
        'role' => ZaaktypeRole::Vergunning->value,
        'zaaktype_identificatie' => 'ZTC-VG-001',
    ]);

    expect(MunicipalityZaaktypeMapping::forZaaktype($zaaktype)?->id)->toBe($mapping->id);
});

test('forZaaktype returns null for a zaaktype without identificatie', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen']);
    $zaaktype = Zaaktype::factory()->create([
        'identificatie' => null,
        'municipality_id' => $heerlen->id,
    ]);

    expect(MunicipalityZaaktypeMapping::forZaaktype($zaaktype))->toBeNull();
});

test('app:backfill-zaaktype-mappings seeds rows from the conventions and is idempotent', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen']);

    Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'identificatie' => 'ZTC-VG',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);
    Zaaktype::factory()->create([
        'name' => 'Melding evenement gemeente Heerlen',
        'identificatie' => 'ZTC-ME',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);
    $doorkomst = Zaaktype::factory()->create([
        'name' => 'Doorkomst gemeente Heerlen',
        'identificatie' => 'ZTC-DK',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);
    $heerlen->doorkomst_zaaktype_id = $doorkomst->id;
    $heerlen->save();

    $this->artisan('app:backfill-zaaktype-mappings')->assertSuccessful();

    expect(MunicipalityZaaktypeMapping::count())->toBe(3);

    $vergunning = MunicipalityZaaktypeMapping::forMunicipalityRole($heerlen, ZaaktypeRole::Vergunning);
    expect($vergunning->zaaktype_identificatie)->toBe('ZTC-VG')
        ->and($vergunning->eigenschap_map)->toBe(ZaakeigenschappenMap::defaultEigenschapMap());

    expect(MunicipalityZaaktypeMapping::forMunicipalityRole($heerlen, ZaaktypeRole::Doorkomst)->zaaktype_identificatie)
        ->toBe('ZTC-DK');

    // Vooraankondiging has no matching zaaktype -> no row seeded for it.
    expect(MunicipalityZaaktypeMapping::forMunicipalityRole($heerlen, ZaaktypeRole::Vooraankondiging))->toBeNull();

    // Re-running creates nothing new.
    $this->artisan('app:backfill-zaaktype-mappings')->assertSuccessful();
    expect(MunicipalityZaaktypeMapping::count())->toBe(3);
});
