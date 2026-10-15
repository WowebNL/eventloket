<?php

declare(strict_types=1);

/**
 * One zaaktype shared by several roles of the same municipality.
 *
 * A municipality may koppel vergunning, melding and vooraankondiging to one
 * and the same zaaktype of its zaaksysteem. The zaaktype then no longer tells
 * the roles apart, so every zaak records its own role, and everything that
 * depends on the role reads it from the zaak: the conversion of a
 * vooraankondiging, the role filters, and the koppeling (statustypen,
 * eigenschappen, documenttypen, route check, hidden resultaattypen) that
 * applies to the zaak.
 */

use App\Actions\UpdateZaakReferenceData;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Organiser\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Jobs\Zaak\CreateConceptAdviceQuestions;
use App\Jobs\Zaak\SetInitialStatusZGW;
use App\Models\Advisory;
use App\Models\DefaultAdviceQuestion;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Organisation;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

function gedeeldZaaktypeUrl(): string
{
    return ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/gedeeld';
}

/**
 * Koppel each role to the shared zaaktype, with per-role settings that differ
 * so a test can tell which koppeling was applied.
 *
 * @param  array<string, array<string, mixed>>  $perRole  role value => extra koppeling columns
 */
function koppelRollenAanGedeeldZaaktype(Municipality $municipality, array $perRole): void
{
    foreach ($perRole as $role => $columns) {
        MunicipalityZaaktypeMapping::create([
            'municipality_id' => $municipality->id,
            'role' => $role,
            'zaaktype_identificatie' => 'SYN-GEDEELD',
            ...$columns,
        ]);
    }
}

function zaakOpGedeeldZaaktype(Zaaktype $zaaktype, Organisation $organisation, ?ZaaktypeRole $role, array $attributes = []): Zaak
{
    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
        'role' => $role,
        ...$attributes,
    ]);
}

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->municipality = Municipality::factory()->create();
    $this->organisation = Organisation::factory()->create();

    // Vergunning is koppeld first on purpose: a lookup that takes "the first
    // koppeling of this zaaktype" lands on it, whatever the zaak is.
    koppelRollenAanGedeeldZaaktype($this->municipality, [
        ZaaktypeRole::Vergunning->value => [
            'initial_statustype' => 'Vergunning ontvangen',
            'triggers_route_check' => true,
            'eigenschap_map' => ['risico_classificatie' => 'risico vergunning'],
        ],
        ZaaktypeRole::Melding->value => [
            'initial_statustype' => 'Melding ontvangen',
            'triggers_route_check' => false,
            'eigenschap_map' => ['risico_classificatie' => 'risico melding'],
        ],
        ZaaktypeRole::Vooraankondiging->value => [
            'initial_statustype' => 'Vooraankondiging ontvangen',
            'triggers_route_check' => false,
        ],
    ]);

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'identificatie' => 'SYN-GEDEELD',
        'name' => 'Evenement behandelen',
        'role' => null,
        'is_active' => true,
        'zgw_zaaktype_url' => gedeeldZaaktypeUrl(),
    ]);

    $this->vergunning = zaakOpGedeeldZaaktype($this->zaaktype, $this->organisation, ZaaktypeRole::Vergunning);
    $this->melding = zaakOpGedeeldZaaktype($this->zaaktype, $this->organisation, ZaaktypeRole::Melding);
    $this->vooraankondiging = zaakOpGedeeldZaaktype($this->zaaktype, $this->organisation, ZaaktypeRole::Vooraankondiging);
});

it('tells a vooraankondiging apart from the other roles on the same zaaktype', function () {
    expect($this->vooraankondiging->refresh()->isVooraankondiging())->toBeTrue()
        ->and($this->vergunning->refresh()->isVooraankondiging())->toBeFalse()
        ->and($this->melding->refresh()->isVooraankondiging())->toBeFalse();
});

it('selects only the vooraankondigingen among zaken on the same zaaktype', function () {
    expect(Zaak::query()->vooraankondigingen()->pluck('id')->all())
        ->toBe([$this->vooraankondiging->id]);
});

it('filters the zaken list on the role of the zaak', function () {
    ZgwHttpFake::wildcardFake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(ListZaken::class)
        ->filterTable('role', [ZaaktypeRole::Melding])
        ->assertCanSeeTableRecords([$this->melding])
        ->assertCanNotSeeTableRecords([$this->vergunning, $this->vooraankondiging]);
});

it('offers the conversion only on the vooraankondiging', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    ZgwHttpFake::fakeSingleZaak('1');
    ZgwHttpFake::fakeSingleZaak('2');
    ZgwHttpFake::wildcardFake();

    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation->users()->attach($organiser, ['role' => OrganisationRole::Admin]);

    $snapshot = ['values' => ['watIsDeNaamVanHetEvenementVergunning' => 'Synthetisch evenement'], 'system' => []];
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1';

    $vooraankondiging = zaakOpGedeeldZaaktype($this->zaaktype, $this->organisation, ZaaktypeRole::Vooraankondiging, [
        'organiser_user_id' => $organiser->id,
        'zgw_zaak_url' => $zaakUrl,
        'form_state_snapshot' => $snapshot,
    ]);
    $vergunning = zaakOpGedeeldZaaktype($this->zaaktype, $this->organisation, ZaaktypeRole::Vergunning, [
        'organiser_user_id' => $organiser->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/2',
        'form_state_snapshot' => $snapshot,
    ]);

    $this->actingAs($organiser);
    Filament::setTenant($this->organisation);

    livewire(ViewZaak::class, ['record' => $vooraankondiging->id])
        ->assertOk()
        ->assertActionVisible('convert_vooraankondiging')
        ->assertActionHidden('prefil_new_request');

    livewire(ViewZaak::class, ['record' => $vergunning->id])
        ->assertOk()
        ->assertActionHidden('convert_vooraankondiging')
        ->assertActionVisible('prefil_new_request');
});

it('applies the koppeling of the zaak role, not the first koppeling of the zaaktype', function () {
    expect($this->melding->refresh()->zaaktypeMapping()?->role)->toBe(ZaaktypeRole::Melding)
        ->and($this->vooraankondiging->refresh()->zaaktypeMapping()?->role)->toBe(ZaaktypeRole::Vooraankondiging)
        ->and($this->vergunning->refresh()->zaaktypeMapping()?->role)->toBe(ZaaktypeRole::Vergunning)
        // The route check is a per-koppeling setting as well.
        ->and($this->vergunning->triggersRouteCheck())->toBeTrue()
        ->and($this->melding->triggersRouteCheck())->toBeFalse();
});

it('sets the initial status the koppeling of the zaak role names', function () {
    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/melding-1';
    $this->melding->update(['zgw_zaak_url' => $zaakUrl, 'zgw_zaaktype_url' => gedeeldZaaktypeUrl()]);

    $statussenUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen';
    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/v', 'omschrijving' => 'Vergunning ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/m', 'omschrijving' => 'Melding ontvangen', 'volgnummer' => 2, 'isEindstatus' => false],
        ], 200),
        $statussenUrl => Http::response(['url' => $statussenUrl.'/new'], 201),
    ]);

    (new SetInitialStatusZGW($this->melding->refresh()))->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/statussen')
        && $request->method() === 'POST'
        && str_ends_with((string) $request['statustype'], '/statustypen/m'));
});

it('reads the risicoclassificatie back through the koppeling of the zaak role, so the default advice questions follow it', function () {
    Queue::fake();

    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1';
    ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'status' => [
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen/1',
                '_expand' => ['statustype' => ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/2', 'omschrijving' => 'In behandeling']],
            ],
            'eigenschappen' => [[
                'uuid' => 'eig-risico',
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen/risico',
                'zaak' => $zaakUrl,
                'eigenschap' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/risico-melding',
                'naam' => 'risico melding',
                'waarde' => 'C',
            ]],
        ],
    ]);

    ZgwHttpFake::wildcardFake();

    $this->melding->update(['zgw_zaak_url' => $zaakUrl]);

    UpdateZaakReferenceData::handle($this->melding->refresh());

    expect($this->melding->refresh()->reference_data->risico_classificatie)->toBe('C');

    $advisory = Advisory::factory()->create();
    DefaultAdviceQuestion::factory()->create([
        'municipality_id' => $this->municipality->id,
        'advisory_id' => $advisory->id,
        'risico_classificatie' => 'C',
        'title' => 'Synthetische adviesvraag',
    ]);

    (new CreateConceptAdviceQuestions($this->melding))->handle();

    expect(AdviceThread::query()->where('zaak_id', $this->melding->id)->pluck('title')->all())
        ->toBe(['Synthetische adviesvraag']);
});

it('falls back to the koppeling of the zaaktype for a zaak without a stored role', function () {
    $zonderRol = zaakOpGedeeldZaaktype($this->zaaktype, $this->organisation, null);

    expect($zonderRol->refresh()->zaaktypeMapping()?->role)
        ->toBe(MunicipalityZaaktypeMapping::forZaaktype($this->zaaktype)?->role);
});

it('does not apply a koppeling that names another zaaktype than the one the zaak uses', function () {
    // A zaak that fell back to a zaaktype of another catalogus: the koppeling
    // of its role names the own zaaktype, so it must not be applied here.
    $andereCatalogus = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'identificatie' => 'SYN-ANDERE-CATALOGUS',
        'is_active' => true,
    ]);

    $zaak = zaakOpGedeeldZaaktype($andereCatalogus, $this->organisation, ZaaktypeRole::Melding);

    expect($zaak->refresh()->zaaktypeMapping())->toBeNull();
});
