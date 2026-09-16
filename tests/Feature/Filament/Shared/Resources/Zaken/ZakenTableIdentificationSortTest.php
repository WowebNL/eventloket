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

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->municipality = Municipality::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->lowestIdentification = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'public_id' => 'ZAAK-0001',
    ]);

    $this->highestIdentification = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'public_id' => 'ZAAK-0002',
    ]);

    $this->withoutIdentification = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'public_id' => null,
    ]);

    $this->actingAs(User::factory()->create([
        'role' => Role::Admin,
    ]));
});

test('sorting by identification descending lists records without an identification last', function () {
    livewire(ListZaken::class)
        ->sortTable('public_id', 'desc')
        ->assertCanSeeTableRecords([
            $this->highestIdentification,
            $this->lowestIdentification,
            $this->withoutIdentification,
        ], inOrder: true);
});

test('sorting by identification ascending lists records without an identification last', function () {
    livewire(ListZaken::class)
        ->sortTable('public_id', 'asc')
        ->assertCanSeeTableRecords([
            $this->lowestIdentification,
            $this->highestIdentification,
            $this->withoutIdentification,
        ], inOrder: true);
});
