<?php

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Organiser\Resources\Zaken\Pages\ListZaken as OrganiserListZaken;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
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
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => Http::response([
            [
                'url' => $catalogiEigenschapUrl,
                'naam' => 'intern_zaaknummer',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'definitie' => 'Intern zaaknummer',
                'specificatie' => [],
            ],
        ], 200),
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

test('deleting intern zaaknummer removes the zaakeigenschap and clears reference data', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $existingEigenschapUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen/existing-eigenschap';

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

    Http::fake([
        $existingEigenschapUrl => Http::response([
            'url' => $existingEigenschapUrl,
            'uuid' => 'existing-eigenschap',
            'zaak' => $zgwZaakUrl,
            'eigenschap' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/intern-zaaknummer',
            'naam' => 'intern_zaaknummer',
            'waarde' => '',
        ], 200),
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
