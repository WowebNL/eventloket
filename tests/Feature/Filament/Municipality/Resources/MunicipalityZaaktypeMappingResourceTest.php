<?php

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\MunicipalityZaaktypeMappingResource;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages\CreateMunicipalityZaaktypeMapping;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages\EditMunicipalityZaaktypeMapping;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

function fakeCatalogus(): void
{
    $base = 'https://zgw.example.com';

    Http::fake([
        "{$base}/catalogi/api/v1/zaaktypen*" => Http::response(ZgwHttpFake::envelope([
            ['identificatie' => 'EVT-1', 'omschrijving' => 'Evenementenvergunning', 'url' => "{$base}/catalogi/api/v1/zaaktypen/1"],
        ])),
        "{$base}/catalogi/api/v1/eigenschappen*" => Http::response(ZgwHttpFake::envelope([
            ['naam' => 'start_evenement'],
            ['naam' => 'naam_evenement'],
        ])),
        "{$base}/catalogi/api/v1/statustypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Ontvangen', 'volgnummer' => 1],
            ['omschrijving' => 'Afgehandeld', 'volgnummer' => 2, 'isEindstatus' => true],
        ])),
        "{$base}/catalogi/api/v1/roltypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Aanvrager', 'omschrijvingGeneriek' => 'initiator'],
        ])),
        "{$base}/catalogi/api/v1/resultaattypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Ingetrokken', 'omschrijvingGeneriek' => 'Ingetrokken'],
        ])),
        "{$base}/catalogi/api/v1/zaaktype-informatieobjecttypen*" => Http::response(ZgwHttpFake::envelope([
            ['informatieobjecttype' => "{$base}/catalogi/api/v1/informatieobjecttypen/1"],
            ['informatieobjecttype' => "{$base}/catalogi/api/v1/informatieobjecttypen/2"],
        ])),
        "{$base}/catalogi/api/v1/informatieobjecttypen/1" => Http::response([
            'url' => "{$base}/catalogi/api/v1/informatieobjecttypen/1",
            'omschrijving' => 'Bijlage',
        ]),
        "{$base}/catalogi/api/v1/informatieobjecttypen/2" => Http::response([
            'url' => "{$base}/catalogi/api/v1/informatieobjecttypen/2",
            'omschrijving' => 'Aanvraag',
        ]),
    ]);
}

beforeEach(function () {
    Cache::flush();
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create();
    $this->beheerder = User::factory()->create(['role' => Role::KoppelingBeheerder]);
    $this->municipality->users()->attach($this->beheerder);

    $this->actingAs($this->beheerder);
    Filament::setTenant($this->municipality);
    Filament::bootCurrentPanel();
});

it('is not accessible to a reviewer', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);
    $this->actingAs($reviewer);

    expect(MunicipalityZaaktypeMappingResource::canAccess())->toBeFalse();
});

it('saves a mapping with eigenschap and flow-blocker selections from the live catalogi', function () {
    fakeCatalogus();

    livewire(CreateMunicipalityZaaktypeMapping::class)
        ->fillForm([
            'role' => ZaaktypeRole::Vergunning->value,
            'zaaktype_identificatie' => 'EVT-1',
            'eigenschap_map' => ['start_evenement' => 'start_evenement'],
            'initial_statustype' => 'Ontvangen',
            'eind_statustype' => 'Afgehandeld',
            'initiator_roltype' => 'Aanvrager',
            'ingetrokken_resultaattype' => 'Ingetrokken',
            'aanvraag_informatieobjecttype' => 'Aanvraag',
            'bijlage_informatieobjecttype' => 'Bijlage',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $mapping = MunicipalityZaaktypeMapping::first();

    expect($mapping->municipality_id)->toBe($this->municipality->id)
        ->and($mapping->role)->toBe(ZaaktypeRole::Vergunning)
        ->and($mapping->zaaktype_identificatie)->toBe('EVT-1')
        ->and($mapping->eigenschap_map)->toBe(['start_evenement' => 'start_evenement'])
        ->and($mapping->initial_statustype)->toBe('Ontvangen')
        ->and($mapping->eind_statustype)->toBe('Afgehandeld')
        ->and($mapping->initiator_roltype)->toBe('Aanvrager')
        ->and($mapping->ingetrokken_resultaattype)->toBe('Ingetrokken')
        ->and($mapping->aanvraag_informatieobjecttype)->toBe('Aanvraag')
        ->and($mapping->bijlage_informatieobjecttype)->toBe('Bijlage');
});

it('does not eagerly read the dependent catalogi when opening the edit form', function () {
    fakeCatalogus();

    $mapping = MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
        'initial_statustype' => 'Ontvangen',
        'initiator_roltype' => 'Aanvrager',
    ]);

    livewire(EditMunicipalityZaaktypeMapping::class, ['record' => $mapping->getKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'zaaktype_identificatie' => 'EVT-1',
            'initial_statustype' => 'Ontvangen',
            'initiator_roltype' => 'Aanvrager',
        ]);

    // The dependent dropdowns fetch their catalogi lazily (only when opened),
    // so merely rendering the edit form must not read them. The zaaktype label
    // is the only catalogi read on load.
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/statustypen'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/roltypen'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/resultaattypen'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/eigenschappen'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/zaaktype-informatieobjecttypen'));
});

it('hides the eind-statustype and ingetrokken-resultaattype when withdrawal is disabled', function () {
    $base = 'https://gemeente.example.com';

    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $this->municipality->id,
        'allow_organiser_withdrawal' => false,
    ]);

    Http::fake([
        "{$base}/catalogi/api/v1/zaaktypen*" => Http::response(ZgwHttpFake::envelope([
            ['identificatie' => 'EVT-1', 'omschrijving' => 'Evenementenvergunning', 'url' => "{$base}/catalogi/api/v1/zaaktypen/1"],
        ])),
        "{$base}/catalogi/api/v1/statustypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Afgehandeld', 'volgnummer' => 1, 'isEindstatus' => true],
        ])),
        "{$base}/catalogi/api/v1/roltypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Aanvrager', 'omschrijvingGeneriek' => 'initiator'],
        ])),
        "{$base}/catalogi/api/v1/resultaattypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Afgebroken', 'omschrijvingGeneriek' => 'Afgebroken'],
        ])),
        "{$base}/catalogi/api/v1/*" => Http::response(ZgwHttpFake::envelope([])),
    ]);

    livewire(CreateMunicipalityZaaktypeMapping::class)
        ->fillForm([
            'role' => ZaaktypeRole::Vergunning->value,
            'zaaktype_identificatie' => 'EVT-1',
        ])
        ->assertFormFieldIsHidden('eind_statustype')
        ->assertFormFieldIsHidden('ingetrokken_resultaattype')
        ->assertFormFieldIsVisible('initial_statustype')
        ->assertFormFieldIsVisible('initiator_roltype');
});

it('hides the eind-statustype and ingetrokken-resultaattype on a OneGround connection', function () {
    $base = 'https://gemeente.example.com';

    // Withdrawal is left enabled; a OneGround connection blocks it regardless.
    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $this->municipality->id,
        'allow_organiser_withdrawal' => true,
        'is_oneground' => true,
    ]);

    Http::fake([
        "{$base}/catalogi/api/v1/zaaktypen*" => Http::response(ZgwHttpFake::envelope([
            ['identificatie' => 'EVT-1', 'omschrijving' => 'Evenementenvergunning', 'url' => "{$base}/catalogi/api/v1/zaaktypen/1"],
        ])),
        "{$base}/catalogi/api/v1/statustypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Afgehandeld', 'volgnummer' => 1, 'isEindstatus' => true],
        ])),
        "{$base}/catalogi/api/v1/roltypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Aanvrager', 'omschrijvingGeneriek' => 'initiator'],
        ])),
        "{$base}/catalogi/api/v1/resultaattypen*" => Http::response(ZgwHttpFake::envelope([
            ['omschrijving' => 'Afgebroken', 'omschrijvingGeneriek' => 'Afgebroken'],
        ])),
        "{$base}/catalogi/api/v1/*" => Http::response(ZgwHttpFake::envelope([])),
    ]);

    livewire(CreateMunicipalityZaaktypeMapping::class)
        ->fillForm([
            'role' => ZaaktypeRole::Vergunning->value,
            'zaaktype_identificatie' => 'EVT-1',
        ])
        ->assertFormFieldIsHidden('eind_statustype')
        ->assertFormFieldIsHidden('ingetrokken_resultaattype')
        ->assertFormFieldIsVisible('initial_statustype')
        ->assertFormFieldIsVisible('initiator_roltype');
});

it('rejects a second mapping for the same role', function () {
    fakeCatalogus();

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
    ]);

    livewire(CreateMunicipalityZaaktypeMapping::class)
        ->fillForm([
            'role' => ZaaktypeRole::Vergunning->value,
            'zaaktype_identificatie' => 'EVT-1',
        ])
        ->call('create')
        ->assertHasFormErrors(['role']);
});

it('re-points a hidden-results selection at the current zaaktype version so the koppeling still saves', function () {
    // Republishing a zaaktype gives every resultaattype a new url. The picker
    // keys its options by url, so a selection stored against the previous
    // version fell outside the options and the form refused to save until it
    // was cleared by hand. The stored url is now followed to its omschrijving
    // and matched onto the current version instead.
    $base = 'https://gemeente.example.com/catalogi/api/v1';

    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    Http::fake([
        // The withdrawn resultaattype of the previous version is still readable
        // by url; it must be matched before the broader list pattern below.
        "{$base}/resultaattypen/old-1" => Http::response([
            'url' => "{$base}/resultaattypen/old-1",
            'omschrijving' => 'Verleend',
        ]),
        "{$base}/zaaktypen*" => Http::response(ZgwHttpFake::envelope([
            ['identificatie' => 'EVT-1', 'omschrijving' => 'Evenementenvergunning', 'url' => "{$base}/zaaktypen/2"],
        ])),
        "{$base}/resultaattypen*" => Http::response(ZgwHttpFake::envelope([
            ['url' => "{$base}/resultaattypen/new-1", 'omschrijving' => 'Verleend'],
            ['url' => "{$base}/resultaattypen/new-2", 'omschrijving' => 'Ingetrokken'],
        ])),
        "{$base}/*" => Http::response(ZgwHttpFake::envelope([])),
    ]);

    $mapping = MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
        'hidden_resultaat_types' => ["{$base}/resultaattypen/old-1"],
    ]);

    // Saving the koppeling without touching anything is what used to fail: the
    // stale url is not among the picker's options, so the checkbox list
    // rejected the untouched selection.
    livewire(EditMunicipalityZaaktypeMapping::class, ['record' => $mapping->getKey()])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertFormSet(['hidden_resultaat_types' => ["{$base}/resultaattypen/new-1"]]);

    expect($mapping->refresh()->hidden_resultaat_types)->toBe(["{$base}/resultaattypen/new-1"]);
});

it('refuses a second koppeling on the same zaaktype', function () {
    fakeCatalogus();

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
    ]);

    livewire(CreateMunicipalityZaaktypeMapping::class)
        ->fillForm([
            'role' => ZaaktypeRole::Vooraankondiging->value,
            'zaaktype_identificatie' => 'EVT-1',
        ])
        ->call('create')
        ->assertHasFormErrors(['zaaktype_identificatie']);

    expect(MunicipalityZaaktypeMapping::count())->toBe(1);
});

it('allows a zaaktype that is only koppeld by another municipality', function () {
    fakeCatalogus();

    // Inserted directly: while a panel tenant is booted, saving a tenant-owned
    // model rewrites its municipality onto that tenant, which would defeat the
    // point of this test.
    DB::table('municipality_zaaktype_mappings')->insert([
        'municipality_id' => Municipality::factory()->createQuietly()->id,
        'role' => ZaaktypeRole::Vergunning->value,
        'zaaktype_identificatie' => 'EVT-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    livewire(CreateMunicipalityZaaktypeMapping::class)
        ->fillForm([
            'role' => ZaaktypeRole::Vooraankondiging->value,
            'zaaktype_identificatie' => 'EVT-1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Counted on the table: model queries are scoped to the current tenant here,
    // and the point of this test is the row of the other municipality.
    expect(DB::table('municipality_zaaktype_mappings')->count())->toBe(2);
});

it('still allows several koppelingen without a chosen zaaktype', function () {
    fakeCatalogus();

    MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => null,
    ]);

    livewire(CreateMunicipalityZaaktypeMapping::class)
        ->fillForm(['role' => ZaaktypeRole::Vooraankondiging->value])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MunicipalityZaaktypeMapping::count())->toBe(2);
});
