<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Organiser\Resources\Zaken\Pages\ListZaken as OrganiserListZaken;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $this->municipality = Municipality::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->admin = User::factory()->create([
        'role' => Role::Admin,
    ]);

    $this->organisation = Organisation::factory()->create([
        'type' => 'business',
        'name' => 'Test organisation',
    ]);

    $this->organiserUser = User::factory()->create([
        'role' => Role::Organiser,
    ]);

    $this->organisation->users()->attach($this->organiserUser, [
        'role' => OrganisationRole::Admin,
    ]);
});

function referenceDataWithInternZaaknummer(?string $internZaaknummer): ZaakReferenceData
{
    return new ZaakReferenceData(
        start_evenement: Carbon::now()->toString(),
        eind_evenement: Carbon::now()->addDay()->toString(),
        registratiedatum: Carbon::now()->toString(),
        status_name: 'Ingediend',
        statustype_url: 'https://example.com/statustype/1',
        naam_evenement: 'Test Event',
        intern_zaaknummer: $internZaaknummer,
    );
}

test('admin sees intern zaaknummer column in zaken table', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    ZgwHttpFake::wildcardFake();

    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => referenceDataWithInternZaaknummer('INT-001'),
    ]);

    $this->actingAs($this->admin);

    livewire(ListZaken::class)
        ->assertOk()
        ->assertSee(__('resources/zaak.columns.intern_zaaknummer.label'));
});

test('coordinator sees intern zaaknummer column in zaken table', function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    ZgwHttpFake::wildcardFake();

    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'reference_data' => referenceDataWithInternZaaknummer('INT-001'),
    ]);

    $coordinator = User::factory()->create(['role' => Role::Coordinator]);
    $coordinator->municipalities()->attach($this->municipality);

    $this->actingAs($coordinator);
    Filament::setTenant($this->municipality);

    livewire(ListZaken::class)
        ->assertOk()
        ->assertSee(__('resources/zaak.columns.intern_zaaknummer.label'));
});

test('organiser does not see intern zaaknummer column in zaken table', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    ZgwHttpFake::wildcardFake();

    Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => referenceDataWithInternZaaknummer('INT-001'),
    ]);

    $this->actingAs($this->organiserUser);
    Filament::setTenant($this->organisation);

    livewire(OrganiserListZaken::class)
        ->assertOk()
        ->assertDontSee(__('resources/zaak.columns.intern_zaaknummer.label'));
});

test('admin sees intern zaaknummer value and edit action in infolist', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => referenceDataWithInternZaaknummer('INT-001'),
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->assertSee(__('resources/zaak.columns.intern_zaaknummer.label'))
        ->assertSee('INT-001')
        ->assertActionExists(TestAction::make('editInternZaaknummer')->schemaComponent('reference_data.intern_zaaknummer'))
        ->assertActionVisible(TestAction::make('deleteInternZaaknummer')->schemaComponent('reference_data.intern_zaaknummer'));
});

test('delete intern zaaknummer action is hidden when not set', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => referenceDataWithInternZaaknummer(null),
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->assertActionDoesNotExist(TestAction::make('deleteInternZaaknummer')->schemaComponent('reference_data.intern_zaaknummer'));
});

test('editing intern zaaknummer creates a new zaakeigenschap and updates reference data', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'eigenschappen' => [],
        ],
    ]);

    $catalogiEigenschapUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/intern-zaaknummer';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => Http::response(ZgwHttpFake::envelope([
            [
                'url' => $catalogiEigenschapUrl,
                'naam' => 'intern_zaaknummer',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'definitie' => 'Intern zaaknummer',
                'specificatie' => [],
            ],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen*' => function (Request $request) use ($zgwZaakUrl, $catalogiEigenschapUrl) {
            return Http::response([
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen/new-eigenschap',
                'uuid' => 'new-eigenschap',
                'zaak' => $zgwZaakUrl,
                'eigenschap' => $catalogiEigenschapUrl,
                'naam' => 'intern_zaaknummer',
                'waarde' => 'INT-999',
            ], 200);
        },
    ]);

    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => referenceDataWithInternZaaknummer(null),
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->callAction(TestAction::make('editInternZaaknummer')->schemaComponent('reference_data.intern_zaaknummer'), data: [
            'intern_zaaknummer' => 'INT-999',
        ])
        ->assertNotified();

    expect($zaak->refresh()->reference_data->intern_zaaknummer)->toBe('INT-999');
});

test('editing intern zaaknummer saves locally when the zaaktype does not know the eigenschap', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'eigenschappen' => [],
        ],
    ]);

    // The catalogus of this zaaktype has no eigenschappen at all.
    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => Http::response(ZgwHttpFake::envelope([]), 200),
    ]);

    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => referenceDataWithInternZaaknummer(null),
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->callAction(TestAction::make('editInternZaaknummer')->schemaComponent('reference_data.intern_zaaknummer'), data: [
            'intern_zaaknummer' => 'INT-777',
        ])
        ->assertNotified(__('municipality/resources/zaak.infolist.sections.actions.actions.edit_intern_zaaknummer.notifications.saved_locally.title'));

    expect($zaak->refresh()->reference_data->intern_zaaknummer)->toBe('INT-777');

    // Nothing was written to the zaaksysteem.
    Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET'
        && str_contains($request->url(), '/zaakeigenschappen'));
});

test('editing intern zaaknummer uses the eigenschap naam from the koppeling', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'eigenschappen' => [],
        ],
    ]);

    $catalogiEigenschapUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/zaaknummer-intern';

    Http::fake([
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => Http::response(ZgwHttpFake::envelope([
            [
                'url' => $catalogiEigenschapUrl,
                'naam' => 'zaaknummer_intern',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'definitie' => 'Intern zaaknummer',
                'specificatie' => [],
            ],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen/new-eigenschap',
            'uuid' => 'new-eigenschap',
            'naam' => 'zaaknummer_intern',
            'waarde' => 'INT-888',
        ], 200),
    ]);

    ZgwHttpFake::wildcardFake();

    $this->zaaktype->update(['identificatie' => 'EVT-1']);
    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
        'eigenschap_map' => ['intern_zaaknummer' => 'zaaknummer_intern'],
    ]));

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => referenceDataWithInternZaaknummer(null),
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->callAction(TestAction::make('editInternZaaknummer')->schemaComponent('reference_data.intern_zaaknummer'), data: [
            'intern_zaaknummer' => 'INT-888',
        ])
        ->assertNotified();

    expect($zaak->refresh()->reference_data->intern_zaaknummer)->toBe('INT-888');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/zaakeigenschappen')
        && ($request->data()['eigenschap'] ?? null) === $catalogiEigenschapUrl);
});

test('deleting intern zaaknummer removes the zaakeigenschap and clears reference data', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $existingEigenschapUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen/existing-eigenschap';

    // ZGW returns 204 No Content for a successful DELETE; the new client requires it.
    // Register this before fakeSingleZaak so its greedy ".../zaken/1*" stub does not
    // catch the zaakeigenschap delete URL first (Http::fake is first-match-wins).
    Http::fake([
        $existingEigenschapUrl => Http::response(null, 204),
    ]);

    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'eigenschappen' => [
                [
                    'uuid' => 'existing-eigenschap',
                    'url' => $existingEigenschapUrl,
                    'zaak' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
                    'eigenschap' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/intern-zaaknummer',
                    'naam' => 'intern_zaaknummer',
                    'waarde' => 'INT-001',
                ],
            ],
        ],
    ]);

    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => referenceDataWithInternZaaknummer('INT-001'),
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->callAction(TestAction::make('deleteInternZaaknummer')->schemaComponent('reference_data.intern_zaaknummer'))
        ->assertNotified();

    expect($zaak->refresh()->reference_data->intern_zaaknummer)->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === $existingEigenschapUrl
        && $request->method() === 'DELETE');
});
