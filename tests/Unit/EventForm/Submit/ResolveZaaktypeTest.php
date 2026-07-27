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

use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;
use App\EventForm\Submit\ResolveZaaktype;
use App\Models\Municipality;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->resolve = new ResolveZaaktype(new DetermineAanvraagType);
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

test('gemeente die niet bij de gevonden locatie hoort → harde fout in plaats van een zaak in de verkeerde gemeente', function () {
    // Vangt de situatie af waarin een verouderde gemeente in de state blijft
    // staan (gekopieerde aanvraag, gewijzigde locatie). Zonder deze controle
    // wordt de zaak stilzwijgend in de oude gemeente aangemaakt.
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);
    Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Heerlen',
        'municipality_id' => $heerlen->id,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'inGemeentenResponse' => ['all' => ['object' => [
            'GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]]],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect(fn () => $this->resolve->forState($state))
        ->toThrow(RuntimeException::class, 'hoort niet bij de gevonden gemeenten');
});

test('gemeente die wel bij de gevonden locatie hoort wordt gewoon gebruikt', function () {
    $maastricht = Municipality::factory()->create(['name' => 'Maastricht', 'brk_identification' => 'GM0935']);
    $verwacht = Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning gemeente Maastricht',
        'municipality_id' => $maastricht->id,
        'is_active' => true,
    ]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0935'],
        'inGemeentenResponse' => ['all' => ['object' => [
            'GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]]],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->resolve->forState($state)->id)->toBe($verwacht->id);
});
