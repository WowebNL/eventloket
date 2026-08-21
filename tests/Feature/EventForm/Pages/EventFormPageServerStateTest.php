<?php

declare(strict_types=1);

/**
 * De locatiecheck (`inGemeentenResponse`) en de daaruit afgeleide gemeente
 * horen bij de server. Ze leven wel in dezelfde values-bag als de
 * formuliervelden, dus ze belandden via `$form->fill()` ook in Livewire's
 * `$data` — waar niets ze meer bijwerkt.
 *
 * Bugrapport: een organisator kopieert een aanvraag zonder route, tekent een
 * route door andere gemeenten en kiest daar één van. De aanvraag kwam toch in
 * de gemeente van het oorspronkelijke evenement terecht. Bij het indienen
 * absorbeerde `submit()` namelijk `$this->form->getStateSnapshot()` (= `$data`)
 * over de state heen, waarmee de locatiecheck terugsprong naar die van het
 * moment van openen. De keuze van de organisator stond dan buiten de
 * gemeentenlijst en werd als verouderd genegeerd, waarna de enige gemeente uit
 * die oude lijst overbleef: de bron-gemeente.
 *
 * Hetzelfde gold voor een hervat concept waarvan de locatie daarna wijzigde.
 */

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Persistence\PrefillLoader;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * De gate-closure van de locatiestap, los aanroepbaar met een echte
 * EventFormPage. Een volledige wizard-navigatie zou alle 18 stappen renderen.
 */
function locatieGate(): Closure
{
    $step = LocatieVanHetEvenement2Step::make();
    $ref = new ReflectionProperty($step, 'afterValidation');
    $ref->setAccessible(true);

    /** @var Closure $callback */
    $callback = $ref->getValue($step);

    return $callback;
}

/** Map-state zoals de kaartcomponent voor een route wegschrijft. */
function routeMapState(array $coordinates): array
{
    return [
        'lat' => 0.0,
        'lng' => 0.0,
        'geojson' => [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => new stdClass,
                'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
            ]],
        ],
    ];
}

/** De locatiecheck-response zoals die na één gevonden gemeente in de state staat. */
function locatieCheckVoor(string $brk, string $naam): array
{
    return [
        'all' => [
            'items' => [['brk_identification' => $brk, 'name' => $naam]],
            'object' => [$brk => ['brk_identification' => $brk, 'name' => $naam]],
            'within' => true,
        ],
    ];
}

/** Wat `submit()` met de state doet vlak voordat het zaaktype wordt bepaald. */
function absorbeerZoalsSubmit(EventFormPage $page): void
{
    $dehydrationState = [];
    $form = $page->getSchema('form');
    $form->callBeforeStateDehydrated($dehydrationState);

    $method = new ReflectionMethod($page, 'absorbFormData');
    $method->setAccessible(true);
    $method->invoke($page, $form->getStateSnapshot());
}

beforeEach(function () {
    $this->bron = Municipality::factory()->create([
        'brk_identification' => 'GM0001', 'name' => 'BronGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM0002', 'name' => 'RouteStart',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM0003', 'name' => 'RouteEind',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
    ]);

    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    // De PDOK-lookup voor het gekopieerde adres levert de bron-gemeente.
    Http::fake(['*' => Http::response([
        'response' => ['docs' => [['gemeentecode' => '0001', 'gemeentenaam' => 'BronGemeente']]],
    ])]);
});

test('kopie van een aanvraag zonder route: de gekozen route-gemeente overleeft het indienen', function () {
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->bron->id, 'is_active' => true]);
    $bronZaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'organiser_user_id' => $this->user->id,
        'form_state_snapshot' => ['values' => [
            'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest',
            'waarVindtHetEvenementPlaats' => ['gebouw'],
            'adresVanDeGebouwEn' => ['row-1' => [
                'naamVanDeLocatieGebouw' => 'Zaal',
                'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                    'postcode' => '6411AA', 'huisnummer' => '1', 'brkGemeente' => 'GM0001',
                    'straatnaam' => 'Straat', 'plaatsnaam' => 'Plaats',
                ],
            ]],
            'inGemeentenResponse' => locatieCheckVoor('GM0001', 'BronGemeente'),
            'evenementInGemeente' => ['brk_identification' => 'GM0001', 'name' => 'BronGemeente'],
        ], 'system' => []],
    ]);

    $state = (new PrefillLoader)->load((string) $bronZaak->id, $this->user, $this->organisation);
    $draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => $state->toSnapshot(),
        'current_step_key' => null,
    ]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $draft->id]);

    // Organisator schakelt over naar een route door GM0002 en GM0003.
    $component->set('data.waarVindtHetEvenementPlaats', ['route']);
    $component->set('data.routesOpKaart', routeMapState([[3.0, 0.0], [6.0, 0.0]]));

    /** @var EventFormPage $page */
    $page = $component->instance();

    // Meerdere gemeenten en nog geen keuze: de poort blokkeert.
    expect(fn () => locatieGate()($page))->toThrow(Halt::class);

    $component->set('data.userSelectGemeente', 'GM0002');
    $page = $component->instance();
    locatieGate()($page);

    expect($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0002');

    // En na de absorb die het indienen doet, staat de keuze er nog steeds.
    absorbeerZoalsSubmit($page);

    expect($page->state()->get('inGemeentenResponse.all.object'))->toHaveKey('GM0002')
        ->and($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0002');
});

test('hervat concept waarvan de route verlegd wordt: het indienen zet de locatiecheck niet terug', function () {
    $draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => ['values' => [
            'watIsDeNaamVanHetEvenementVergunning' => 'Optocht',
            'waarVindtHetEvenementPlaats' => ['route'],
            'routesOpKaart' => routeMapState([[-0.5, 0.0], [0.5, 0.0]]), // binnen GM0001
            'inGemeentenResponse' => locatieCheckVoor('GM0001', 'BronGemeente'),
        ], 'system' => []],
        'current_step_key' => null,
    ]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $draft->id]);
    $component->set('data.routesOpKaart', routeMapState([[3.0, 0.0], [6.0, 0.0]]));

    /** @var EventFormPage $page */
    $page = $component->instance();

    expect(fn () => locatieGate()($page))->toThrow(Halt::class);

    $component->set('data.userSelectGemeente', 'GM0003');
    $page = $component->instance();
    locatieGate()($page);

    absorbeerZoalsSubmit($page);

    expect($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0003');
});

test('een gemanipuleerde locatiecheck in de client-data stuurt de aanvraag niet naar een andere gemeente', function () {
    $draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => ['values' => [
            'waarVindtHetEvenementPlaats' => ['route'],
            'routesOpKaart' => routeMapState([[-0.5, 0.0], [0.5, 0.0]]), // binnen GM0001
        ], 'system' => []],
        'current_step_key' => null,
    ]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $draft->id]);

    /** @var EventFormPage $page */
    $page = $component->instance();
    locatieGate()($page);

    expect($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0001');

    // Client schuift een eigen locatiecheck plus gemeente naar binnen.
    $page->data['inGemeentenResponse'] = locatieCheckVoor('GM0003', 'RouteEind');
    $page->data['evenementInGemeente'] = ['brk_identification' => 'GM0003', 'name' => 'RouteEind'];

    absorbeerZoalsSubmit($page);

    expect($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0001');
});

test('unticking a location kind takes the copied address out of the municipality choice', function () {
    // A copy of an application that took place in a building. Unticking "in a
    // building" leaves the address in the hidden form state — Filament keeps
    // the raw value of a hidden component and `absorbFields()` merges — so the
    // tick boxes are the only thing still saying the organiser dropped it.
    $draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => ['values' => [
            'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest',
            'waarVindtHetEvenementPlaats' => ['gebouw'],
            'adresVanDeGebouwEn' => ['row-1' => [
                'naamVanDeLocatieGebouw' => 'Zaal',
                'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                    'postcode' => '6411AA', 'huisnummer' => '1', 'brkGemeente' => 'GM0001',
                    'straatnaam' => 'Straat', 'plaatsnaam' => 'Plaats',
                ],
            ]],
            'inGemeentenResponse' => locatieCheckVoor('GM0001', 'BronGemeente'),
            'evenementInGemeente' => ['brk_identification' => 'GM0001', 'name' => 'BronGemeente'],
        ], 'system' => []],
        'current_step_key' => null,
    ]);

    $component = Livewire::test(EventFormPage::class, ['draft' => $draft->id]);

    // The unticking alone must already drop the source municipality.
    $component->set('data.waarVindtHetEvenementPlaats', ['route']);

    /** @var EventFormPage $page */
    $page = $component->instance();
    expect($page->state()->get('gemeenten'))->toBeNull();

    // And with a fresh route through a single municipality there is nothing
    // left to choose: the gate lets the organiser through without asking, and
    // without the source municipality.
    $component->set('data.routesOpKaart', routeMapState([[2.5, 0.0], [3.5, 0.0]]));
    $page = $component->instance();
    locatieGate()($page);

    expect(array_keys((array) $page->state()->get('inGemeentenResponse.all.object')))->toBe(['GM0002'])
        ->and($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0002');
});
