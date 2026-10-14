<?php

use App\Enums\Role;
use App\Filament\Shared\Resources\Zaken\Pages\ListZaken;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    ZgwHttpFake::wildcardFake();

    $this->first = Municipality::factory()->create(['name' => 'Eerste gemeente']);
    $this->second = Municipality::factory()->create(['name' => 'Tweede gemeente']);

    $this->firstZaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create(['municipality_id' => $this->first->id])->id,
    ]);
    $this->secondZaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create(['municipality_id' => $this->second->id])->id,
    ]);
});

test('the platform administrator sees which municipality a zaak belongs to', function () {
    // The administrator's list spans every municipality, so without this column
    // the rows of two municipalities are indistinguishable.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(ListZaken::class)
        ->assertOk()
        ->assertSee(__('resources/zaak.columns.municipality.label'))
        ->assertSee('Eerste gemeente')
        ->assertSee('Tweede gemeente');
});

test('the platform administrator can narrow the list to one municipality', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(ListZaken::class)
        ->assertCanSeeTableRecords([$this->firstZaak, $this->secondZaak])
        ->filterTable('municipality', [$this->first->id])
        ->assertCanSeeTableRecords([$this->firstZaak])
        ->assertCanNotSeeTableRecords([$this->secondZaak]);
});

test('a municipality user gets neither the column nor the filter', function () {
    // Inside a municipality panel every zaak belongs to the tenant, so the
    // column would repeat one value and the filter would do nothing.
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $coordinator = User::factory()->create(['role' => Role::Coordinator]);
    $coordinator->municipalities()->attach($this->first);

    $this->actingAs($coordinator);
    Filament::setTenant($this->first);

    livewire(ListZaken::class)
        ->assertOk()
        ->assertTableColumnHidden('municipality.name')
        ->assertTableFilterHidden('municipality');
});
