<?php

use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\ValueObjects\PostbusAddress;

test('PostbusAddressCast persists and retrieves postbus address', function () {
    $organisation = Organisation::factory()->postbus('321', '2222BB', 'Eindhoven')->create([
        'type' => OrganisationType::Business,
    ]);

    $fresh = $organisation->fresh();

    expect($fresh->postbus_address)->toBeInstanceOf(PostbusAddress::class)
        ->and($fresh->postbus_address->postbusnummer)->toBe('321')
        ->and($fresh->postbus_address->postcode)->toBe('2222BB')
        ->and($fresh->postbus_address->woonplaatsnaam)->toBe('Eindhoven');
});

test('postbus_address is null when not set', function () {
    $organisation = Organisation::factory()->create(['postbus_address' => null]);

    expect($organisation->fresh()->postbus_address)->toBeNull();
});

test('isPostbus returns true for postbus organisations', function () {
    $organisation = Organisation::factory()->postbus()->create();

    expect($organisation->isPostbus())->toBeTrue();
});

test('isPostbus returns false for non-postbus organisations', function () {
    $organisation = Organisation::factory()->create(['postbus_address' => null]);

    expect($organisation->isPostbus())->toBeFalse();
});

test('PostbusAddressCast weergavenaam matches stored address column', function () {
    $organisation = Organisation::factory()->postbus('555', '7777XY', 'Maastricht')->create();

    expect($organisation->address)->toBe($organisation->postbus_address->weergavenaam());
});
