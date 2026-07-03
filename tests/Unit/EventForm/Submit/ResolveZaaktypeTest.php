<?php

/**
 * `ResolveZaaktype` koppelt een aanvraag aan het juiste `Zaaktype` in
 * de database. De combinatie is `(gemeente × aard)`:
 *
 *   - aard komt uit `DetermineAanvraagType`
 *   - gemeente komt uit `evenementInGemeente.brk_identification` (de
 *     BRK-code zoals "GM0882" die LocationServerCheckService zet)
 *   - zaaktypes hebben de naamconventie
 *     "{Evenementenvergunning|Melding|Vooraankondiging} ... gemeente {X}",
 *     die conventie is bevestigd in de database (13 gemeenten × 3
 *     aanvraag-typen) en afkomstig uit SyncZaaktypen.
 *
 * Deze tests gebruiken echte Municipality+Zaaktype-rijen (via factory)
 * omdat de lookup een Eloquent-query is — fake-objecten zouden te ver
 * van de werkelijkheid staan.
 */

use App\Enums\ZaaktypeRole;
use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;
use App\EventForm\Submit\ResolveZaaktype;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaaktype;
use App\Services\Zgw\ZaaktypeMainFallback;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolve = new ResolveZaaktype(new DetermineAanvraagType, new ZaaktypeMainFallback);
});

test('vergunning voor Heerlen → Evenementenvergunning-zaaktype van Heerlen', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);
    Zaaktype::factory()->create([
        'name' => 'Melding evenement gemeente Heerlen',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);
    $verwacht = Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($verwacht->id);
});

test('melding voor Maastricht → Melding-zaaktype van Maastricht', function () {
    $maastricht = Municipality::factory()->create(['name' => 'Maastricht', 'brk_identification' => 'GM0935']);
    Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Maastricht',
        'municipality_id' => $maastricht->id,
        'is_active' => true,
    ]);
    $verwacht = Zaaktype::factory()->create([
        'name' => 'Melding evenement gemeente Maastricht',
        'municipality_id' => $maastricht->id,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0935'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($verwacht->id);
});

test('inactieve zaaktypes worden overgeslagen', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);
    Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'municipality_id' => $heerlen->id,
        'is_active' => false, // inactief
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect(fn () => $this->resolve->forState($state))
        ->toThrow(RuntimeException::class, 'Geen actief zaaktype');
});

test('resolveert op de expliciete role-kolom, ook als de naam niet de conventie volgt', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    // A name that does not match the prefix convention, but tagged with the role.
    $verwacht = Zaaktype::factory()->create([
        'name' => 'Aanvraag groot evenement Heerlen',
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($verwacht->id);
});

test('geen gemeente herleidbaar uit state → exception', function () {
    $state = new FormState(values: [
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect(fn () => $this->resolve->forState($state))
        ->toThrow(RuntimeException::class, 'Geen gemeente herleidbaar');
});

test('gemeente uit state matcht niets in de DB → exception', function () {
    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM9999'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect(fn () => $this->resolve->forState($state))
        ->toThrow(RuntimeException::class);
});

test('valt terug op de gekoppelde main-rij als het eigen zaaktype inactief is', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $heerlen->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'OWN-1',
    ]));

    // The mapped own-instance row lost its valid version and was deactivated.
    Zaaktype::factory()->create([
        'name' => 'Eigen evenementenvergunning',
        'identificatie' => 'OWN-1',
        'connection' => "gemeente_{$heerlen->id}",
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $heerlen->id,
        'is_active' => false,
    ]);

    $fallback = Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'connection' => 'main',
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($fallback->id);
});

test('een weer actief eigen zaaktype wint van een nog gekoppelde main-fallback', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $heerlen->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'OWN-1',
    ]));

    $eigen = Zaaktype::factory()->create([
        'name' => 'Eigen evenementenvergunning',
        'identificatie' => 'OWN-1',
        'connection' => "gemeente_{$heerlen->id}",
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    // The fallback link deliberately survives a restore (zaken created during
    // the fallback derive their municipality through this row).
    Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'connection' => 'main',
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($eigen->id);
});

test('eigen-instantie-gemeente zonder gekoppeld type valt terug op de main-rij', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    // Heerlen runs its own ZGW instance.
    MunicipalityZgwConnection::factory()->create(['municipality_id' => $heerlen->id]);

    // Its own instance couples Vergunning, but not Melding.
    Zaaktype::factory()->create([
        'name' => 'Eigen evenementenvergunning',
        'connection' => "gemeente_{$heerlen->id}",
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    // The main catalogus still has a Melding row for Heerlen, unlinked because
    // Heerlen runs its own instance (SyncZaaktypen skips linking it).
    $mainMelding = Zaaktype::factory()->create([
        'name' => 'Melding evenement gemeente Heerlen',
        'connection' => 'main',
        'role' => ZaaktypeRole::Melding,
        'municipality_id' => null,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee', // → Melding
    ]);

    $resolved = $this->resolve->forState($state);

    expect($resolved->id)->toBe($mainMelding->id);
    // The main row is now linked to Heerlen so a zaak created on it derives its
    // municipality through the zaaktype.
    expect($resolved->fresh()->municipality_id)->toBe($heerlen->id);
});

test('een main-gemeente zonder gekoppeld type valt niet terug via de main-fallback', function () {
    // A municipality without its own instance whose main row is (mis)configured
    // as unlinked must not silently get linked by the resolver; it still throws.
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    Zaaktype::factory()->create([
        'name' => 'Melding evenement gemeente Heerlen',
        'connection' => 'main',
        'role' => ZaaktypeRole::Melding,
        'municipality_id' => null,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);

    expect(fn () => $this->resolve->forState($state))
        ->toThrow(RuntimeException::class, 'Geen actief zaaktype');
});

test('eigen-connectie-rij wint ook op de role-route als beide gekoppeld en actief zijn', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    // No mapping: resolution goes through the role column for both rows.
    Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'connection' => 'main',
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    $eigen = Zaaktype::factory()->create([
        'name' => 'Eigen evenementenvergunning',
        'identificatie' => 'OWN-1',
        'connection' => "gemeente_{$heerlen->id}",
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($eigen->id);
});
