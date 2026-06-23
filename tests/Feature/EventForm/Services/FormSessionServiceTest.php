<?php

declare(strict_types=1);

use App\EventForm\Services\FormSessionService;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->service = new FormSessionService;
});

test('builds the expected payload shape', function () {
    $organisation = Organisation::factory()->create([
        'name' => 'Woweb Events',
        'coc_number' => '12345678',
        'email' => 'events@woweb.nl',
        'phone' => '0612345678',
    ]);
    $user = User::factory()->create([
        'email' => 'dion@woweb.nl',
        'phone' => '0687654321',
        'first_name' => 'Dion',
        'last_name' => 'Snoeijen',
    ]);

    $result = $this->service->buildFor($user, $organisation);

    expect($result)->toMatchArray([
        'kvk' => '12345678',
        'organisation_name' => 'Woweb Events',
        'organisation_email' => 'events@woweb.nl',
        'organisation_phone' => '0612345678',
        'user_email' => 'dion@woweb.nl',
        'user_phone' => '0687654321',
        'user_first_name' => 'Dion',
        'user_last_name' => 'Snoeijen',
    ])
        ->and($result['user_uuid'])->toBe($user->uuid)
        ->and($result['organiser_uuid'])->toBe($organisation->uuid);
});

test('returns empty string for organisation_address when no address on file', function () {
    $organisation = Organisation::factory()->create([
        'bag_id' => null,
        'postbus_address' => null,
    ]);
    $user = User::factory()->create();

    $result = $this->service->buildFor($user, $organisation);

    expect($result['organisation_address'])->toBe('');
});

test('handles missing email/phone on organisation gracefully', function () {
    $organisation = Organisation::factory()->create([
        'email' => null,
        'phone' => null,
        'coc_number' => null,
    ]);
    $user = User::factory()->create();

    $result = $this->service->buildFor($user, $organisation);

    expect($result['organisation_email'])->toBe('')
        ->and($result['organisation_phone'])->toBe('')
        ->and($result['kvk'])->toBe('');
});
