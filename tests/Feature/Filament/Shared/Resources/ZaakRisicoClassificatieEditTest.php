<?php

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
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
});

function referenceDataForRisico(?string $classificatie, ?string $toelichting = null): ZaakReferenceData
{
    return new ZaakReferenceData(
        start_evenement: Carbon::now()->toString(),
        eind_evenement: Carbon::now()->addDay()->toString(),
        registratiedatum: Carbon::now()->toString(),
        status_name: 'Ingediend',
        statustype_url: 'https://example.com/statustype/1',
        naam_evenement: 'Test Event',
        risico_classificatie: $classificatie,
        risico_toelichting: $toelichting,
    );
}

/**
 * Fake GET response for the zaak, expanding the eigenschappen currently known.
 * The reference is shared with the caller so the response reflects eigenschappen
 * created earlier in the same test (mimicking the backend state after a store).
 */
function fakeZaakResponse(string $zgwZaakUrl, array &$eigenschappen): Closure
{
    return function () use ($zgwZaakUrl, &$eigenschappen) {
        return Http::response([
            'uuid' => '1',
            'url' => $zgwZaakUrl,
            'identificatie' => 'ZAAK-123',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
            'omschrijving' => 'Test zaak',
            'startdatum' => now()->toIso8601String(),
            'registratiedatum' => now()->toIso8601String(),
            'einddatum' => null,
            'einddatumGepland' => null,
            'uiterlijkeEinddatumAfdoening' => null,
            'bronorganisatie' => '123',
            'zaakgeometrie' => null,
            '_expand' => [
                'eigenschappen' => $eigenschappen,
            ],
        ], 200);
    };
}

function fakeCatalogiRisicoEigenschappen(): array
{
    $base = ZgwHttpFake::$baseUrl;

    return [
        [
            'url' => $base.'/catalogi/api/v1/eigenschappen/risico-classificatie',
            'naam' => 'risico_classificatie',
            'zaaktype' => $base.'/catalogi/api/v1/zaaktypen/1',
            'definitie' => 'Risico classificatie',
            'specificatie' => [],
        ],
        [
            'url' => $base.'/catalogi/api/v1/eigenschappen/risico-toelichting',
            'naam' => 'risico_toelichting',
            'zaaktype' => $base.'/catalogi/api/v1/zaaktypen/1',
            'definitie' => 'Risico toelichting',
            'specificatie' => [],
        ],
    ];
}

test('a second risico classificatie edit in the same session patches instead of creating a duplicate eigenschap', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $base = ZgwHttpFake::$baseUrl;
    $zgwZaakUrl = $base.'/zaken/api/v1/zaken/1';

    // Grows as the store branch is taken; the zaak GET reflects it, so the
    // second edit can only "see" the created eigenschappen if the ZGW cache
    // was cleared after the first edit.
    $eigenschappen = [];
    $storeCount = 0;

    Http::fake([
        // Most specific first: the nested zaakeigenschappen endpoint.
        $base.'/zaken/api/v1/zaken/1/zaakeigenschappen*' => function (Request $request) use (&$eigenschappen, &$storeCount, $zgwZaakUrl, $base) {
            if ($request->method() === 'POST') {
                $storeCount++;
                $body = $request->data();
                $naam = str_contains($body['eigenschap'], 'toelichting') ? 'risico_toelichting' : 'risico_classificatie';

                $item = [
                    'uuid' => $naam.'-uuid',
                    'url' => $base.'/zaken/api/v1/zaken/1/zaakeigenschappen/'.$naam,
                    'zaak' => $zgwZaakUrl,
                    'eigenschap' => $body['eigenschap'],
                    'naam' => $naam,
                    'waarde' => (string) $body['waarde'],
                ];
                $eigenschappen[] = $item;

                return Http::response($item, 201);
            }

            // PATCH: echo the eigenschap back with the new value.
            return Http::response([
                'url' => $request->url(),
                'waarde' => (string) ($request->data()['waarde'] ?? ''),
            ], 200);
        },
        $base.'/catalogi/api/v1/eigenschappen*' => Http::response(fakeCatalogiRisicoEigenschappen(), 200),
        $zgwZaakUrl.'*' => fakeZaakResponse($zgwZaakUrl, $eigenschappen),
        // Catch-all for any other ZGW read triggered while rendering the page.
        $base.'*' => Http::response([], 200),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => referenceDataForRisico(null),
    ]);

    $this->actingAs($this->admin);

    $component = livewire(ViewZaak::class, ['record' => $zaak->id])->assertOk();

    // First edit: no eigenschappen exist yet, so both are created (2 stores).
    $component
        ->callAction(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'), data: [
            'risico_classificatie' => 'A',
            'risico_toelichting' => 'Eerste reden',
        ])
        ->assertNotified();

    // Second edit in the same session: the eigenschappen now exist. With a
    // cleared cache the action re-reads them and patches; without it, it works
    // on a stale (empty) cache and stores duplicates.
    $component
        ->callAction(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'), data: [
            'risico_classificatie' => 'B',
            'risico_toelichting' => 'Tweede reden',
        ])
        ->assertNotified();

    // Only the first edit may create eigenschappen (classificatie + toelichting).
    expect($storeCount)->toBe(2);
    expect($zaak->refresh()->reference_data->risico_classificatie)->toBe('B');
});

test('a failing zgw call shows a danger notification instead of an uncaught exception', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $base = ZgwHttpFake::$baseUrl;
    $zgwZaakUrl = $base.'/zaken/api/v1/zaken/1';
    $eigenschappen = [];

    Http::fake([
        // The store call fails, so the ZGW client throws a RequestException.
        $base.'/zaken/api/v1/zaken/1/zaakeigenschappen*' => Http::response(['detail' => 'server error'], 500),
        $base.'/catalogi/api/v1/eigenschappen*' => Http::response(fakeCatalogiRisicoEigenschappen(), 200),
        $zgwZaakUrl.'*' => fakeZaakResponse($zgwZaakUrl, $eigenschappen),
        $base.'*' => Http::response([], 200),
    ]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => referenceDataForRisico(null),
    ]);

    $this->actingAs($this->admin);

    // Without the try/catch this call bubbles the RequestException up as an
    // uncaught exception; with it, the action reports and shows a notification.
    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->callAction(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'), data: [
            'risico_classificatie' => 'A',
            'risico_toelichting' => 'Reden',
        ])
        ->assertNotified();

    // The backend failed before the local reference could be persisted, so the
    // stored value must be untouched (no half-applied change).
    expect($zaak->refresh()->reference_data->risico_classificatie)->toBeNull();
});
