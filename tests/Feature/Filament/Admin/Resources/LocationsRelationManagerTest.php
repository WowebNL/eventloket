<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\MunicipalityResource\Pages\EditMunicipality;
use App\Filament\Admin\Resources\MunicipalityResource\RelationManagers\LocationsRelationManager;
use App\Models\Location;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    actingAs($admin);
});

test('admin can view locations relation manager on municipality edit page', function () {
    $municipality = Municipality::factory()->create();
    $locations = Location::factory()->count(3)->create([
        'municipality_id' => $municipality->id,
    ]);

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($locations);
});

test('admin can create location through relation manager', function () {
    $municipality = Municipality::factory()->create();

    $formData = [
        'name' => 'New Location via Relation',
        'postal_code' => '1234AB',
        'house_number' => '50',
        'house_letter' => 'C',
        'street_name' => 'Relation Street',
        'city_name' => 'Utrecht',
        'active' => true,
        'geometry' => [
            'lat' => 51.41319724,
            'lng' => 5.43674043,
            'geojson' => [
                'features' => [
                    [
                        'properties' => [],
                        'type' => 'Feature',
                        'geometry' => [
                            'coordinates' => [5.43674043, 51.41319724],
                            'type' => 'Point',
                        ],
                    ],
                ],
                'type' => 'FeatureCollection',
            ],
        ],
    ];

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->callTableAction('create', null, $formData)
        ->assertSuccessful();

    $this->assertDatabaseHas(Location::class, [
        'municipality_id' => $municipality->id,
        'name' => 'New Location via Relation',
        'postal_code' => '1234AB',
        'house_number' => '50',
        'street_name' => 'Relation Street',
    ]);
});

test('admin can edit location through relation manager', function () {
    $municipality = Municipality::factory()->create();
    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Original Location',
    ]);

    $formData = [
        'name' => 'Edited Location via Relation',
        'postal_code' => $location->postal_code,
        'house_number' => $location->house_number,
        'street_name' => $location->street_name,
        'active' => false,
    ];

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->callTableAction('edit', $location, $formData)
        ->assertSuccessful();

    $this->assertDatabaseHas(Location::class, [
        'id' => $location->id,
        'name' => 'Edited Location via Relation',
        'active' => false,
    ]);
});

// test('admin can delete location through relation manager', function () {
//    $municipality = Municipality::factory()->create();
//    $location = Location::factory()->create([
//        'municipality_id' => $municipality->id,
//    ]);
//
//    livewire(LocationsRelationManager::class, [
//        'ownerRecord' => $municipality,
//        'pageClass' => EditMunicipality::class,
//    ])
//        ->assertOk()
//        ->callTableAction('delete', $location)
//        ->assertSuccessful();
//
//    $this->assertModelMissing($location);
// });

test('locations relation manager shows only locations for specific municipality', function () {
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    $municipality1Locations = Location::factory()->count(2)->create([
        'municipality_id' => $municipality1->id,
    ]);

    $municipality2Locations = Location::factory()->count(2)->create([
        'municipality_id' => $municipality2->id,
    ]);

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality1,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($municipality1Locations)
        ->assertCanNotSeeTableRecords($municipality2Locations);
});

test('locations relation manager displays address column', function () {
    $municipality = Municipality::factory()->create();
    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'street_name' => 'Test Avenue',
        'house_number' => '456',
        'house_letter' => 'B',
        'house_number_addition' => 'II',
        'postal_code' => '2000AA',
        'city_name' => 'Den Haag',
    ]);

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$location])
        ->assertTableColumnStateSet('address', 'Test Avenue 456BII, 2000AA Den Haag', $location);
});

test('locations relation manager displays active status', function () {
    $municipality = Municipality::factory()->create();
    $activeLocation = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'active' => true,
    ]);
    $inactiveLocation = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'active' => false,
    ]);

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$activeLocation, $inactiveLocation])
        ->assertTableColumnStateSet('active', true, $activeLocation)
        ->assertTableColumnStateSet('active', false, $inactiveLocation);
});

test('locations relation manager allows bulk delete', function () {
    $municipality = Municipality::factory()->create();
    $locations = Location::factory()->count(3)->create([
        'municipality_id' => $municipality->id,
    ]);

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->callTableBulkAction('delete', $locations)
        ->assertSuccessful();

    foreach ($locations as $location) {
        $this->assertModelMissing($location);
    }
});

test('locations relation manager validates required fields on create', function () {
    $municipality = Municipality::factory()->create();

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->callTableAction('create', null, [
            'name' => '',
            'postal_code' => '',
            'house_number' => '',
            'street_name' => '',
        ])
        ->assertHasTableActionErrors([
            'name' => 'required',
            'geometry' => 'required',
        ]);
});

test('locations relation manager allows searching by name', function () {
    $municipality = Municipality::factory()->create();
    $searchableLocation = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Unique Search Term Hall',
    ]);
    $otherLocation = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Different Name',
    ]);

    livewire(LocationsRelationManager::class, [
        'ownerRecord' => $municipality,
        'pageClass' => EditMunicipality::class,
    ])
        ->assertOk()
        ->searchTable('Unique Search Term')
        ->assertCanSeeTableRecords([$searchableLocation])
        ->assertCanNotSeeTableRecords([$otherLocation]);
});
