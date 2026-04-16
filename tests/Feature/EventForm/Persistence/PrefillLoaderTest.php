<?php

declare(strict_types=1);

use App\Enums\Role;
use App\EventForm\Persistence\PrefillLoader;
use App\Models\Organisation;
use App\Models\User;
use Woweb\Openzaak\ObjectsApi;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->objectsApi = Mockery::mock(ObjectsApi::class);
    $this->loader = new PrefillLoader($this->objectsApi);
});

test('returns null when reference is null', function () {
    $result = $this->loader->load(null, $this->user, $this->organisation);

    expect($result)->toBeNull();
});

test('returns null when the record owner does not match the current user', function () {
    $otherUserUuid = 'ffffffff-aaaa-bbbb-cccc-000000000000';
    $this->objectsApi->shouldReceive('get')->with('abc-123')->once()->andReturn(collect([
        'record' => [
            'data' => [
                'user_uuid' => $otherUserUuid,
                'organiser_uuid' => $this->organisation->uuid,
                'watIsUwVoornaam' => 'Eva',
            ],
        ],
    ]));

    $result = $this->loader->load('abc-123', $this->user, $this->organisation);

    expect($result)->toBeNull();
});

test('returns null when the record organisation does not match the current tenant', function () {
    $this->objectsApi->shouldReceive('get')->with('abc-123')->once()->andReturn(collect([
        'record' => [
            'data' => [
                'user_uuid' => $this->user->uuid,
                'organiser_uuid' => 'eeeeeeee-aaaa-bbbb-cccc-000000000000',
                'watIsUwVoornaam' => 'Eva',
            ],
        ],
    ]));

    $result = $this->loader->load('abc-123', $this->user, $this->organisation);

    expect($result)->toBeNull();
});

test('returns a FormState with prefill variables when the reference is valid', function () {
    $recordData = [
        'user_uuid' => $this->user->uuid,
        'organiser_uuid' => $this->organisation->uuid,
        'watIsUwVoornaam' => 'Eva',
        'watIsUwAchternaam' => 'Janssen',
        'watIsUwEMailadres' => 'eva@example.nl',
    ];
    $this->objectsApi->shouldReceive('get')->with('abc-123')->once()->andReturn(collect([
        'record' => [
            'data' => $recordData,
        ],
    ]));

    $state = $this->loader->load('abc-123', $this->user, $this->organisation);

    expect($state)->not->toBeNull()
        ->and($state->get('eventloketPrefillLoaded'))->toBeTrue()
        ->and($state->get('eventloketPrefill.watIsUwVoornaam'))->toBe('Eva')
        ->and($state->get('eventloketPrefill.watIsUwAchternaam'))->toBe('Janssen')
        ->and($state->get('prefill.user_uuid'))->toBe($this->user->uuid);
});

test('returns null when the record is missing the required ownership keys', function () {
    $this->objectsApi->shouldReceive('get')->with('abc-123')->once()->andReturn(collect([
        'record' => [
            'data' => [
                'watIsUwVoornaam' => 'Eva',
            ],
        ],
    ]));

    $result = $this->loader->load('abc-123', $this->user, $this->organisation);

    expect($result)->toBeNull();
});
