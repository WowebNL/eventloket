<?php

declare(strict_types=1);

/**
 * The risico classificatie is assessed for the event on the hoofdzaak. A
 * doorkomst zaak does not carry it: the zaak view neither shows it nor offers
 * the action to change it. Every other zaak keeps both.
 */

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->municipality = Municipality::factory()->create();
    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

function zaakWithRisicoClassificatieForRole(string $zaaktypeId, ?ZaaktypeRole $role): Zaak
{
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('1');
    ZgwHttpFake::wildcardFake();

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktypeId,
        'zgw_zaak_url' => $zgwZaakUrl,
        'role' => $role,
        'reference_data' => new ZaakReferenceData(
            start_evenement: Carbon::now()->toString(),
            eind_evenement: Carbon::now()->addDay()->toString(),
            registratiedatum: Carbon::now()->toString(),
            status_name: 'Ingediend',
            statustype_url: 'https://example.com/statustype/1',
            naam_evenement: 'Test Event',
            risico_classificatie: 'C',
            risico_toelichting: 'Synthetic toelichting',
        ),
    ]);
}

test('a doorkomst zaak neither shows nor edits the risico classificatie', function () {
    $zaak = zaakWithRisicoClassificatieForRole($this->zaaktype->id, ZaaktypeRole::Doorkomst);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->assertSchemaComponentHidden('reference_data.risico_classificatie')
        ->assertActionDoesNotExist(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'))
        ->assertDontSeeText(__('resources/zaak.columns.risico_classificatie.label'));

    expect($zaak->hasRisicoClassificatie())->toBeFalse()
        ->and($zaak->behandelaarCanEditRisicoClassificatie())->toBeFalse();
});

test('a doorkomst zaak whose role comes from its zaaktype hides the risico classificatie as well', function () {
    $this->zaaktype->update(['name' => 'Doorkomst evenement']);
    $zaak = zaakWithRisicoClassificatieForRole($this->zaaktype->id, null);

    expect($zaak->effectiveRole())->toBe(ZaaktypeRole::Doorkomst)
        ->and($zaak->hasRisicoClassificatie())->toBeFalse()
        ->and($zaak->behandelaarCanEditRisicoClassificatie())->toBeFalse();
});

test('any other zaak shows and edits the risico classificatie', function (ZaaktypeRole $role) {
    $zaak = zaakWithRisicoClassificatieForRole($this->zaaktype->id, $role);

    $this->actingAs($this->admin);

    livewire(ViewZaak::class, ['record' => $zaak->id])
        ->assertOk()
        ->assertSchemaComponentVisible('reference_data.risico_classificatie')
        ->assertActionVisible(TestAction::make('editRisicoClassificatie')->schemaComponent('reference_data.risico_classificatie'))
        ->assertSeeText(__('resources/zaak.columns.risico_classificatie.label'));

    expect($zaak->hasRisicoClassificatie())->toBeTrue()
        ->and($zaak->behandelaarCanEditRisicoClassificatie())->toBeTrue();
})->with([
    'vergunning' => [ZaaktypeRole::Vergunning],
    'melding' => [ZaaktypeRole::Melding],
    'vooraankondiging' => [ZaaktypeRole::Vooraankondiging],
]);
