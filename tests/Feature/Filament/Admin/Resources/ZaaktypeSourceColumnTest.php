<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\Zaaktypes\Pages\ListZaaktypes;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use App\Models\Zaaktype;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->create(['role' => Role::Admin]));
});

test('the zaaktype table shows the source instance per zaaktype', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen']);
    MunicipalityZgwConnection::factory()->create(['municipality_id' => $heerlen->id, 'name' => 'Heerlen ZGW']);

    $mainZaaktype = Zaaktype::factory()->create(['connection' => 'main', 'municipality_id' => null]);
    $ownZaaktype = Zaaktype::factory()->create([
        'connection' => "gemeente_{$heerlen->id}",
        'municipality_id' => $heerlen->id,
    ]);

    livewire(ListZaaktypes::class)
        ->assertOk()
        ->assertTableColumnStateSet('connection', __('admin/resources/zaaktype.columns.connection.main'), $mainZaaktype)
        ->assertTableColumnStateSet('connection', 'Heerlen ZGW', $ownZaaktype);
});

test('the connection filter narrows the zaaktype list to one source', function () {
    $heerlen = Municipality::factory()->create(['name' => 'Heerlen']);
    MunicipalityZgwConnection::factory()->create(['municipality_id' => $heerlen->id, 'name' => 'Heerlen ZGW']);

    $mainZaaktype = Zaaktype::factory()->create(['connection' => 'main', 'municipality_id' => null]);
    $ownZaaktype = Zaaktype::factory()->create([
        'connection' => "gemeente_{$heerlen->id}",
        'municipality_id' => $heerlen->id,
    ]);

    livewire(ListZaaktypes::class)
        ->filterTable('connection', "gemeente_{$heerlen->id}")
        ->assertCanSeeTableRecords([$ownZaaktype])
        ->assertCanNotSeeTableRecords([$mainZaaktype]);
});
