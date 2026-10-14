<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\MunicipalityResource\Pages\ListMunicipalities;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->create(['role' => Role::Admin]));
});

test('the municipality list shows the ZGW instance indicator', function () {
    $own = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->create(['municipality_id' => $own->id, 'name' => 'Eigen ZGW']);

    $shared = Municipality::factory()->create();

    livewire(ListMunicipalities::class)
        ->assertOk()
        ->assertTableColumnStateSet('zgwConnection', 'Eigen ZGW', $own)
        ->assertTableColumnStateSet('zgwConnection', __('admin/resources/municipality.columns.zgw_instance.shared'), $shared);
});
