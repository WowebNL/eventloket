<?php

use App\Enums\Role;
use App\Models\Location;
use App\Models\Municipality;
use App\Models\User;

test('allows admins to manage locations across all municipalities', function () {
    // arrange: admin user, two municipalities, a location
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    // act: admin creates a location
    $location = Location::factory()->create([
        'municipality_id' => $municipality1->id,
        'name' => 'City Hall',
        'postal_code' => '1234AB',
        'house_number' => '1',
        'street_name' => 'Main Street',
        'city_name' => 'Amsterdam',
        'active' => true,
    ]);

    // assert: admin can view and update the location
    expect($admin->can('view', $location))->toBe(true);
    expect($admin->can('update', $location))->toBe(true);

    // act: admin updates the location
    $location->update([
        'name' => 'New City Hall',
        'active' => false,
    ]);

    // assert: changes are persisted
    expect($location->fresh())
        ->name->toBe('New City Hall')
        ->active->toBe(false)
        ->municipality_id->toBe($municipality1->id);

    // act: admin deletes the location
    expect($admin->can('delete', $location))->toBe(true);
    $location->delete();

    // assert: location is deleted
    expect(Location::find($location->id))->toBeNull();
});

test('restricts location access to admins of the given municipality', function () {
    // arrange: user not admin of this municipality
    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    $municipalityAdmin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);
    $municipalityAdmin->municipalities()->attach($municipality1);

    $location = Location::factory()->create([
        'municipality_id' => $municipality2->id,
        'name' => 'Test Location',
        'postal_code' => '1234AB',
        'house_number' => '1',
        'street_name' => 'Test Street',
        'active' => true,
    ]);

    // act & assert: try to view/update/delete location - policy should deny
    expect($municipalityAdmin->can('view', $location))->toBe(false);
    expect($municipalityAdmin->can('update', $location))->toBe(false);
    expect($municipalityAdmin->can('delete', $location))->toBe(false);
});

test('allows municipality admins to fully manage their own locations', function () {
    // arrange: municipality admin with location
    $municipality = Municipality::factory()->create();
    $municipalityAdmin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);
    $municipalityAdmin->municipalities()->attach($municipality);

    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Original Name',
        'postal_code' => '1234AB',
        'house_number' => '1',
        'street_name' => 'Original Street',
        'active' => true,
    ]);

    // act: check permissions
    expect($municipalityAdmin->can('view', $location))->toBe(true);
    expect($municipalityAdmin->can('update', $location))->toBe(true);
    expect($municipalityAdmin->can('delete', $location))->toBe(true);

    // act: update location
    $location->update([
        'name' => 'Updated Name',
        'street_name' => 'Updated Street',
        'active' => false,
    ]);

    // assert: changes persisted
    expect($location->fresh())
        ->name->toBe('Updated Name')
        ->street_name->toBe('Updated Street')
        ->active->toBe(false);
});

test('location belongs to municipality relationship', function () {
    // arrange
    $municipality = Municipality::factory()->create();
    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
    ]);

    // assert: relationship works
    expect($location->municipality)->toBeInstanceOf(Municipality::class)
        ->and($location->municipality->id)->toBe($municipality->id);
});

test('municipality has many locations relationship', function () {
    // arrange
    $municipality = Municipality::factory()->create();
    $locations = Location::factory()->count(3)->create([
        'municipality_id' => $municipality->id,
    ]);

    // assert: relationship works
    $municipalityLocations = $municipality->locations;
    expect($municipalityLocations)->toHaveCount(3);
    expect($municipalityLocations->pluck('id')->sort()->values()->toArray())
        ->toBe($locations->pluck('id')->sort()->values()->toArray());
});

test('location casts geometry to json', function () {
    // arrange
    $municipality = Municipality::factory()->create();
    $geometryData = [
        'type' => 'Polygon',
        'coordinates' => [[[4.9, 52.3], [4.91, 52.3], [4.91, 52.31], [4.9, 52.31], [4.9, 52.3]]],
    ];

    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'geometry' => $geometryData,
    ]);

    // assert: geometry is cast to array/json
    expect($location->geometry)->toBe($geometryData);

    // assert: after refresh
    $location->refresh();
    expect($location->geometry)->toBe($geometryData);
});

test('location casts active to boolean', function () {
    // arrange
    $municipality = Municipality::factory()->create();

    $activeLocation = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'active' => true,
    ]);

    $inactiveLocation = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'active' => false,
    ]);

    // assert: active is boolean
    expect($activeLocation->active)->toBe(true);
    expect($inactiveLocation->active)->toBe(false);
});

test('location validates required fields', function () {
    // arrange
    $municipality = Municipality::factory()->create();

    // act & assert: missing required fields should throw exception
    expect(function () use ($municipality) {
        Location::create([
            'municipality_id' => $municipality->id,
            // Missing name, postal_code, house_number, street_name
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

test('location allows optional fields to be null', function () {
    // arrange & act
    $municipality = Municipality::factory()->create();
    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Test Location',
        'postal_code' => '1234AB',
        'house_number' => '1',
        'street_name' => 'Test Street',
        'house_letter' => null,
        'house_number_addition' => null,
        'city_name' => null,
        'geometry' => null,
        'active' => true,
    ]);

    // assert: optional fields are null
    expect($location)
        ->house_letter->toBeNull()
        ->house_number_addition->toBeNull()
        ->city_name->toBeNull()
        ->geometry->toBeNull();
});

test('reviewer municipality admin can also manage locations', function () {
    // arrange
    $municipality = Municipality::factory()->create();
    $reviewerAdmin = User::factory()->create([
        'email' => 'reviewer@example.com',
        'role' => Role::ReviewerMunicipalityAdmin,
    ]);
    $reviewerAdmin->municipalities()->attach($municipality);

    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
    ]);

    // assert: reviewer admin has permissions
    expect($reviewerAdmin->can('view', $location))->toBe(true);
    expect($reviewerAdmin->can('update', $location))->toBe(true);
    expect($reviewerAdmin->can('delete', $location))->toBe(true);
    expect($reviewerAdmin->can('create', Location::class))->toBe(true);
});

test('non-municipality users cannot access locations', function () {
    // arrange
    $municipality = Municipality::factory()->create();
    $organiser = User::factory()->create([
        'email' => 'organiser@example.com',
        'role' => Role::Organiser,
    ]);

    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
    ]);

    // assert: organiser has no permissions
    expect($organiser->can('viewAny', Location::class))->toBe(false);
    expect($organiser->can('view', $location))->toBe(false);
    expect($organiser->can('create', Location::class))->toBe(false);
    expect($organiser->can('update', $location))->toBe(false);
    expect($organiser->can('delete', $location))->toBe(false);
});

test('location can be created with complete address information', function () {
    // arrange & act
    $municipality = Municipality::factory()->create();
    $location = Location::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Complete Address Hall',
        'postal_code' => '1011AB',
        'house_number' => '123',
        'house_letter' => 'A',
        'house_number_addition' => 'bis',
        'street_name' => 'Damrak',
        'city_name' => 'Amsterdam',
        'active' => true,
    ]);

    // assert: all address fields are correctly stored
    expect($location)
        ->name->toBe('Complete Address Hall')
        ->postal_code->toBe('1011AB')
        ->house_number->toBe('123')
        ->house_letter->toBe('A')
        ->house_number_addition->toBe('bis')
        ->street_name->toBe('Damrak')
        ->city_name->toBe('Amsterdam')
        ->active->toBe(true);
});

test('admin can view all locations regardless of municipality', function () {
    // arrange
    $admin = User::factory()->create([
        'role' => Role::Admin,
    ]);

    $municipality1 = Municipality::factory()->create();
    $municipality2 = Municipality::factory()->create();

    $location1 = Location::factory()->create(['municipality_id' => $municipality1->id]);
    $location2 = Location::factory()->create(['municipality_id' => $municipality2->id]);

    // assert: admin can view both
    expect($admin->can('view', $location1))->toBe(true);
    expect($admin->can('view', $location2))->toBe(true);
});
