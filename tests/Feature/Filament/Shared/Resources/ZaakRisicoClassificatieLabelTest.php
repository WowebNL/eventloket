<?php

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

const EXPECTED_CLASSIFICATION_OPTIONS = [
    '0' => 'M',
    'A' => 'A',
    'B' => 'B',
    'C' => 'C',
];

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->municipality = Municipality::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

function zaakWithClassification(string $zaaktypeId, ?string $classification, string $zaakUuid = '1'): Zaak
{
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak($zaakUuid);
    ZgwHttpFake::wildcardFake();

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktypeId,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
            risico_classificatie: $classification,
        ),
    ]);
}

test('the risk classification edit dropdown offers M for the manual classification', function () {
    $zaak = zaakWithClassification($this->zaaktype->id, '0');

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->mountAction(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'))
        ->assertSchemaComponentExists(
            'risico_classificatie',
            checkComponentUsing: fn (Select $select) => $select->getOptions() === EXPECTED_CLASSIFICATION_OPTIONS,
        );
});

test('the zaken table filter offers M for the manual classification', function () {
    zaakWithClassification($this->zaaktype->id, '0');

    $this->actingAs($this->admin);

    livewire(ListZaken::class)
        ->assertOk()
        ->assertTableFilterExists(
            'reference_data.risico_classificatie',
            fn (SelectFilter $filter) => $filter->getOptions() === EXPECTED_CLASSIFICATION_OPTIONS,
        );
});

test('the infolist shows M for the manual classification', function () {
    $zaak = zaakWithClassification($this->zaaktype->id, '0');

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->assertSchemaComponentExists(
            'reference_data.risico_classificatie',
            checkComponentUsing: fn (TextEntry $entry) => $entry->formatState('0') === 'M'
                && $entry->formatState('B') === 'B',
        );
});

test('the zaken table shows M for the manual classification', function () {
    $zaak = zaakWithClassification($this->zaaktype->id, '0');

    $this->actingAs($this->admin);

    livewire(ListZaken::class)
        ->assertOk()
        ->assertTableColumnExists(
            'reference_data.risico_classificatie',
            fn (TextColumn $column) => $column->formatState('0') === 'M',
        )
        ->assertTableColumnFormattedStateSet('reference_data.risico_classificatie', 'M', $zaak);
});

test('a calculated classification is shown unchanged', function () {
    $zaak = zaakWithClassification($this->zaaktype->id, 'C');

    $this->actingAs($this->admin);

    livewire(ListZaken::class)
        ->assertOk()
        ->assertTableColumnFormattedStateSet('reference_data.risico_classificatie', 'C', $zaak);
});

test('choosing M stores the underlying value 0', function () {
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('1', [
        '_expand' => [
            'eigenschappen' => [],
        ],
    ]);

    $catalogiBaseUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen';

    Http::fake([
        $catalogiBaseUrl.'*' => Http::response([
            [
                'url' => $catalogiBaseUrl.'/risico-classificatie',
                'naam' => 'risico_classificatie',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'definitie' => 'Risicoclassificatie',
                'specificatie' => [],
            ],
            [
                'url' => $catalogiBaseUrl.'/risico-toelichting',
                'naam' => 'risico_toelichting',
                'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
                'definitie' => 'Toelichting',
                'specificatie' => [],
            ],
        ], 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen*' => fn (Request $request) => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1/zaakeigenschappen/new',
            'uuid' => 'new',
            'zaak' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/1',
            'eigenschap' => $catalogiBaseUrl.'/risico-classificatie',
            'naam' => 'risico_classificatie',
            'waarde' => '0',
        ], 200),
    ]);

    ZgwHttpFake::wildcardFake();

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
            risico_classificatie: 'C',
        ),
    ]);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->callAction(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'), data: [
            'risico_classificatie' => '0',
            'risico_toelichting' => 'Handmatig vastgesteld',
        ]);

    expect($zaak->refresh()->reference_data->risico_classificatie)->toBe('0');
});
