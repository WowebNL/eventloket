<?php

/**
 * The organiser threads tab embeds a relation manager that checks the
 * OrganiserThread viewAny() policy before it mounts. A role that may open the
 * zaak but not list organiser threads must get the zaak page without the tab,
 * not a refused page.
 */

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ViewZaak;
use App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers\OrganiserThreadsRelationManager;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak();
    ZgwHttpFake::wildcardFake();

    $this->municipality = Municipality::factory()->create();

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create(['municipality_id' => $this->municipality->id])->id,
        'organisation_id' => Organisation::factory()->create()->id,
        'zgw_zaak_url' => $zgwZaakUrl,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
});

function viewZaakAsMunicipalityRole(Role $role, Municipality $municipality, Zaak $zaak)
{
    $user = User::factory()->create(['role' => $role]);
    $user->municipalities()->attach($municipality);
    test()->actingAs($user);
    Filament::setTenant($municipality);

    return livewire(ViewZaak::class, ['record' => $zaak->id]);
}

test('a koppeling beheerder opens the zaak page without the organiser threads tab', function () {
    viewZaakAsMunicipalityRole(Role::KoppelingBeheerder, $this->municipality, $this->zaak)
        ->assertOk()
        ->assertDontSeeLivewire(OrganiserThreadsRelationManager::class)
        ->assertDontSee(__('municipality/resources/zaak.infolist.tabs.messages.label'));
});

test('a reviewer still gets the organiser threads tab', function () {
    viewZaakAsMunicipalityRole(Role::Reviewer, $this->municipality, $this->zaak)
        ->assertOk()
        ->assertSee(__('municipality/resources/zaak.infolist.tabs.messages.label'));
});
