<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\Locations\Pages\CreateLocation;
use App\Filament\Municipality\Clusters\Settings\Resources\Locations\Pages\EditLocation;
use App\Filament\Municipality\Clusters\Settings\Resources\Locations\Pages\ListLocations;
use App\Models\Location;
use App\Models\Municipality;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->municipalityAdmin = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($this->municipalityAdmin);

    $this->actingAs($this->municipalityAdmin);
    Filament::setTenant($this->municipality);
});

test('municipality admin can view location resource', function () {
    $locations = Location::factory()->count(3)->create([
        'municipality_id' => $this->municipality->id,
    ]);

    livewire(ListLocations::class)
        ->assertOk()
        ->assertCanSeeTableRecords($locations);
});

// test('municipality admin can only see their own municipality locations', function () {
//    $ownLocations = Location::factory()->count(2)->create([
//        'municipality_id' => $this->municipality->id,
//    ]);
//
//    $otherMunicipality = Municipality::factory()->create();
//    $otherLocations = Location::factory()->count(2)->create([
//        'municipality_id' => $otherMunicipality->id,
//    ]);
//
//    livewire(ListLocations::class)
//        ->assertOk()
//        ->assertCanSeeTableRecords($ownLocations)
//        ->assertCanNotSeeTableRecords($otherLocations);
// });

// test('municipality admin can create location', function () {
//    $formData = [
//        'name' => 'New Event Hall',
//        'postal_code' => '1234AB',
//        'house_number' => '42',
//        'house_letter' => 'A',
//        'house_number_addition' => 'bis',
//        'street_name' => 'Main Street',
//        'city_name' => 'Amsterdam',
//        'active' => true,
//        'geometry' => null,
//    ];
//
//    livewire(CreateLocation::class)
//        ->fillForm($formData)
//        ->call('create')
//        ->assertHasNoFormErrors();
//
//    $this->assertDatabaseHas(Location::class, [
//        'municipality_id' => $this->municipality->id,
//        'name' => 'New Event Hall',
//        'postal_code' => '1234AB',
//        'house_number' => '42',
//        'house_letter' => 'A',
//        'house_number_addition' => 'bis',
//        'street_name' => 'Main Street',
//        'city_name' => 'Amsterdam',
//        'active' => true,
//    ]);
// });

// test('municipality admin can create location with minimal fields', function () {
//    $formData = [
//        'name' => 'Simple Location',
//        'postal_code' => '5678CD',
//        'house_number' => '1',
//        'street_name' => 'Simple Street',
//        'active' => true,
//    ];
//
//    livewire(CreateLocation::class)
//        ->fillForm($formData)
//        ->call('create')
//        ->assertHasNoFormErrors();
//
//    $this->assertDatabaseHas(Location::class, [
//        'municipality_id' => $this->municipality->id,
//        'name' => 'Simple Location',
//        'postal_code' => '5678CD',
//        'house_number' => '1',
//        'street_name' => 'Simple Street',
//    ]);
// });

test('municipality admin can edit location', function () {
    $location = Location::factory()->create([
        'municipality_id' => $this->municipality->id,
        'name' => 'Original Name',
        'postal_code' => '1234AB',
        'house_number' => '1',
        'street_name' => 'Original Street',
        'active' => true,
    ]);

    $formData = [
        'name' => 'Updated Name',
        'postal_code' => '5678CD',
        'house_number' => '99',
        'house_letter' => 'B',
        'house_number_addition' => 'III',
        'street_name' => 'Updated Street',
        'city_name' => 'Rotterdam',
        'active' => false,
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

    livewire(EditLocation::class, [
        'record' => $location->getRouteKey(),
    ])
        ->fillForm($formData)
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Location::class, [
        'id' => $location->id,
        'name' => 'Updated Name',
        'postal_code' => '5678CD',
        'house_number' => '99',
        'house_letter' => 'B',
        'house_number_addition' => 'III',
        'street_name' => 'Updated Street',
        'city_name' => 'Rotterdam',
        'active' => false,
    ]);
});

test('municipality admin can delete location', function () {
    $location = Location::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    livewire(EditLocation::class, [
        'record' => $location->getRouteKey(),
    ])
        ->assertOk()
        ->callAction(DeleteAction::class)
        ->assertSuccessful();

    $this->assertModelMissing($location);
});

test('location form validates required fields', function () {
    livewire(CreateLocation::class)
        ->fillForm([
            'name' => '',
            'postal_code' => '',
            'house_number' => '',
            'street_name' => '',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'geometry' => 'required',
        ]);
});

// test('location form accepts optional fields as empty', function () {
//    livewire(CreateLocation::class)
//        ->fillForm([
//            'name' => 'Test Location',
//            'postal_code' => '1234AB',
//            'house_number' => '1',
//            'street_name' => 'Test Street',
//            'house_letter' => null,
//            'house_number_addition' => null,
//            'city_name' => null,
//            'active' => true,
//        ])
//        ->call('create')
//        ->assertHasNoFormErrors();
// });

test('location table displays address column correctly', function () {
    $location = Location::factory()->create([
        'municipality_id' => $this->municipality->id,
        'name' => 'Test Location',
        'street_name' => 'Damrak',
        'house_number' => '123',
        'house_letter' => 'A',
        'house_number_addition' => 'bis',
        'postal_code' => '1011AB',
        'city_name' => 'Amsterdam',
    ]);

    livewire(ListLocations::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$location])
        ->assertTableColumnStateSet('address', 'Damrak 123Abis, 1011AB Amsterdam', $location);
});

test('location table shows active status with icon', function () {
    $activeLocation = Location::factory()->create([
        'municipality_id' => $this->municipality->id,
        'active' => true,
    ]);

    $inactiveLocation = Location::factory()->create([
        'municipality_id' => $this->municipality->id,
        'active' => false,
    ]);

    livewire(ListLocations::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$activeLocation, $inactiveLocation])
        ->assertTableColumnStateSet('active', true, $activeLocation)
        ->assertTableColumnStateSet('active', false, $inactiveLocation);
});

test('location table allows searching by name', function () {
    $searchableLocation = Location::factory()->create([
        'municipality_id' => $this->municipality->id,
        'name' => 'Unique Event Hall',
    ]);

    $otherLocation = Location::factory()->create([
        'municipality_id' => $this->municipality->id,
        'name' => 'Different Location',
    ]);

    livewire(ListLocations::class)
        ->searchTable('Unique Event Hall')
        ->assertCanSeeTableRecords([$searchableLocation])
        ->assertCanNotSeeTableRecords([$otherLocation]);
});

test('location can be bulk deleted', function () {
    $locations = Location::factory()->count(3)->create([
        'municipality_id' => $this->municipality->id,
    ]);

    livewire(ListLocations::class)
        ->callTableBulkAction('delete', $locations)
        ->assertSuccessful();

    foreach ($locations as $location) {
        $this->assertModelMissing($location);
    }
});

test('reviewer municipality admin can also manage locations', function () {
    $reviewerAdmin = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($reviewerAdmin);

    $this->actingAs($reviewerAdmin);
    Filament::setTenant($this->municipality);

    $location = Location::factory()->create([
        'municipality_id' => $this->municipality->id,
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
    ]);

    livewire(ListLocations::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$location]);

    livewire(EditLocation::class, [
        'record' => $location->getRouteKey(),
    ])
        ->assertOk()
        ->fillForm(['name' => 'Updated by Reviewer'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Location::class, [
        'id' => $location->id,
        'name' => 'Updated by Reviewer',
    ]);
});

test('location form has geometry map field', function () {
    livewire(CreateLocation::class)
        ->assertFormFieldExists('geometry');
});

// test('location can be created with geometry data', function () {
//    $geometryData = [
//        'type' => 'Polygon',
//        'coordinates' => [[[4.9, 52.3], [4.91, 52.3], [4.91, 52.31], [4.9, 52.31], [4.9, 52.3]]],
//    ];
//
//    livewire(CreateLocation::class)
//        ->fillForm([
//            'name' => 'Location with Geometry',
//            'postal_code' => '1234AB',
//            'house_number' => '1',
//            'street_name' => 'Test Street',
//            'active' => true,
//            'geometry' => $geometryData,
//        ])
//        ->call('create')
//        ->assertHasNoFormErrors();
//
//    $location = Location::where('name', 'Location with Geometry')->first();
//    expect($location->geometry)->toBe($geometryData);
// });

test('location active toggle defaults to true', function () {
    livewire(CreateLocation::class)
        ->assertFormFieldIsVisible('active')
        ->assertFormSet([
            'active' => true,
        ]);
});
