<?php

declare(strict_types=1);

/**
 * The behandelaar action that edits the risicoclassificatie writes two
 * zaakeigenschappen. A catalogus may name them differently, so the action has
 * to resolve both names through the koppeling instead of assuming the logical
 * keys, otherwise it cannot find the existing eigenschappen and the edit fails.
 */

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'identificatie' => 'EVT-1',
    ]);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

/**
 * Fake a zaak that already carries both risico eigenschappen under the given
 * namen, plus a catalogus that only knows those namen.
 *
 * @param  array{0: string, 1: string}  $namen  classificatie naam, toelichting naam
 */
function fakeZaakWithRisicoEigenschappen(array $namen): string
{
    [$classificatieNaam, $toelichtingNaam] = $namen;

    $zaakUrl = ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1';
    $eigenschapBase = $zaakUrl.'/zaakeigenschappen/';

    // Registered before fakeSingleZaak: its greedy ".../zaken/1*" stub would
    // otherwise catch these URLs first (Http::fake is first-match-wins).
    Http::fake([
        $eigenschapBase.'*' => Http::response(['waarde' => 'ok'], 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => Http::response(ZgwHttpFake::envelope([
            [
                'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/classificatie',
                'naam' => $classificatieNaam,
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'definitie' => 'Risicoclassificatie',
                'specificatie' => [],
            ],
            [
                'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/toelichting',
                'naam' => $toelichtingNaam,
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'definitie' => 'Toelichting',
                'specificatie' => [],
            ],
        ]), 200),
    ]);

    ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'eigenschappen' => [
                [
                    'uuid' => 'classificatie-1',
                    'url' => $eigenschapBase.'classificatie-1',
                    'zaak' => $zaakUrl,
                    'eigenschap' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/classificatie',
                    'naam' => $classificatieNaam,
                    'waarde' => 'A',
                ],
                [
                    'uuid' => 'toelichting-1',
                    'url' => $eigenschapBase.'toelichting-1',
                    'zaak' => $zaakUrl,
                    'eigenschap' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/toelichting',
                    'naam' => $toelichtingNaam,
                    'waarde' => 'Oude toelichting',
                ],
            ],
        ],
    ]);

    ZgwHttpFake::wildcardFake();

    return $zaakUrl;
}

test('editing the risicoclassificatie updates the eigenschappen named by the koppeling', function () {
    $zaakUrl = fakeZaakWithRisicoEigenschappen(['1.risico klasse', '1.risico toelichting']);

    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $this->municipality->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'EVT-1',
        'eigenschap_map' => [
            'risico_classificatie' => '1.risico klasse',
            'risico_toelichting' => '1.risico toelichting',
        ],
    ]));

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->callAction(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'), data: [
            'risico_classificatie' => 'C',
            'risico_toelichting' => 'Nieuwe toelichting',
        ])
        ->assertNotified(__('Risico classificatie en toelichting zijn gewijzigd'));

    $referenceData = $zaak->refresh()->reference_data;

    expect($referenceData->risico_classificatie)->toBe('C')
        ->and($referenceData->risico_toelichting)->toBe('Nieuwe toelichting');

    // The existing eigenschappen are patched; no duplicates are created.
    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/zaakeigenschappen/classificatie-1')
        && ($request->data()['waarde'] ?? null) === 'C');
    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/zaakeigenschappen/toelichting-1')
        && ($request->data()['waarde'] ?? null) === 'Nieuwe toelichting');
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), '/zaakeigenschappen'));
});

test('editing the risicoclassificatie keeps working without a koppeling', function () {
    $zaakUrl = fakeZaakWithRisicoEigenschappen(['risico_classificatie', 'risico_toelichting']);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zaakUrl,
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->callAction(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'), data: [
            'risico_classificatie' => 'B',
            'risico_toelichting' => 'Toelichting zonder koppeling',
        ])
        ->assertNotified(__('Risico classificatie en toelichting zijn gewijzigd'));

    expect($zaak->refresh()->reference_data->risico_classificatie)->toBe('B');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/zaakeigenschappen/classificatie-1'));
});
