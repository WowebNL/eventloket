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
use App\EventForm\Submit\ResolveZaaktype;
use App\Exceptions\GemeenteLocatieMismatchException;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Resolved from the container: the resolver also needs the (singleton)
    // ZgwConnectionResolver to keep the zaaktype on the connection the zaak
    // will be created on.
    $this->resolve = app(ResolveZaaktype::class);
});

/**
 * A municipality that runs its own ZGW instance, with an own-instance zaaktype
 * for the Vergunning role and the matching main-catalogus row available as a
 * fallback. Returns both rows.
 *
 * @return array{0: Municipality, 1: Zaaktype, 2: Zaaktype}
 */
function fallbackSetup(array $connectionAttributes = [], bool $withMainRow = true): array
{
    $municipality = Municipality::factory()->create(['name' => 'Heerlen', 'brk_identification' => 'GM0917']);

    MunicipalityZgwConnection::factory()->create(array_merge(
        ['municipality_id' => $municipality->id],
        $connectionAttributes,
    ));

    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'OWN-1',
    ]));

    $own = Zaaktype::factory()->create([
        'name' => 'Eigen evenementenvergunning',
        'identificatie' => 'OWN-1',
        'connection' => "gemeente_{$municipality->id}",
        'zgw_zaaktype_url' => 'https://gemeente.example.com/catalogi/api/v1/zaaktypen/own',
        'role' => ZaaktypeRole::Vergunning,
        'municipality_id' => $municipality->id,
        'is_active' => true,
    ]);

    $main = $withMainRow
        ? Zaaktype::factory()->create([
            'name' => 'Evenementenvergunning gemeente Heerlen',
            'identificatie' => 'MAIN-1',
            'connection' => 'main',
            'zgw_zaaktype_url' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/main',
            'role' => ZaaktypeRole::Vergunning,
            'municipality_id' => null,
            'is_active' => true,
        ])
        : $own;

    return [$municipality, $own, $main];
}

function fallbackVergunningState(): FormState
{
    return new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM0917'],
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);
}

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

    // The own-instance row only wins while the connection it names is usable;
    // an own-instance row without a live connection deliberately falls back.
    MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $heerlen->id]);

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

    MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $heerlen->id]);

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

test('gemeente die niet bij de gevonden locatie hoort → harde fout in plaats van een zaak in de verkeerde gemeente', function () {
    // Vangt de situatie af waarin een verouderde gemeente in de state blijft
    // staan (gekopieerde aanvraag, gewijzigde locatie). Zonder deze controle
    // wordt de zaak stilzwijgend in de oude gemeente en op diens ZGW-instantie
    // aangemaakt.
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

    // Een eigen exceptietype, zodat de submit-handler de organisator naar de
    // locatiestap kan sturen in plaats van de generieke "probeer het opnieuw".
    expect(fn () => $this->resolve->forState($state))
        ->toThrow(GemeenteLocatieMismatchException::class, 'hoort niet bij de gevonden gemeenten');
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

/**
 * The crossing this whole change is about: an own-instance zaaktype row while
 * the runtime connection of that municipality falls back to main. The three
 * triggers below are the three ways the resolver reaches that fallback.
 *
 * Without the fallback the zaak is created on main with a zaaktype url from the
 * municipality's own catalogus, which the main instance cannot resolve.
 */
test('een nooit geactiveerde koppeling laat het zaaktype meevallen naar main', function () {
    // Trigger (b): the koppeling is configured (which already creates an active
    // own-instance zaaktype row) but was never activated.
    [, , $main] = fallbackSetup(['activated_at' => null]);

    expect($this->resolve->forState(fallbackVergunningState())->id)->toBe($main->id);
});

test('een gedeactiveerde koppeling laat het zaaktype meevallen naar main', function () {
    // Trigger (a): the koppeling was live and has been switched off, by hand or
    // by a change to one of the critical fields.
    [, , $main] = fallbackSetup();

    MunicipalityZgwConnection::query()->firstOrFail()->update(['activated_at' => null]);

    expect($this->resolve->forState(fallbackVergunningState())->id)->toBe($main->id);
});

test('een actieve koppeling met onbruikbare config laat het zaaktype meevallen naar main', function () {
    // Trigger (c): the row presents itself as live, but its config cannot be
    // built, so the resolver routes the zaak to main anyway.
    [, , $main] = fallbackSetup(['activated_at' => now(), 'client_secret' => 'te-kort']);

    expect($this->resolve->forState(fallbackVergunningState())->id)->toBe($main->id);
});

test('een bruikbare actieve koppeling houdt het eigen zaaktype', function () {
    [, $own] = fallbackSetup(['activated_at' => now()]);

    expect($this->resolve->forState(fallbackVergunningState())->id)->toBe($own->id);
});

test('zonder main-tegenhanger blijft de eigen rij staan, zodat de zaak-stap luid faalt', function () {
    // Nothing to fall back to: keeping the own row lets CreateZaakInZGW refuse
    // the submit with a readable error instead of posting a zaaktype the main
    // instance does not know.
    [, $own] = fallbackSetup(['activated_at' => null], withMainRow: false);

    expect($this->resolve->forState(fallbackVergunningState())->id)->toBe($own->id);
});
