<?php

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\MunicipalityZaaktypeMappingResource;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityZaaktypeMappings\Pages\CreateMunicipalityZaaktypeMapping;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
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
        ])),
        "{$base}/catalogi/api/v1/informatieobjecttypen/1" => Http::response([
            'url' => "{$base}/catalogi/api/v1/informatieobjecttypen/1",
            'omschrijving' => 'Bijlage',
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
        ->and($mapping->bijlage_informatieobjecttype)->toBe('Bijlage');
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
