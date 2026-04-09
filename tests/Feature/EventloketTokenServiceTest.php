<?php

use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EventloketTokenService;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['services.open_forms.token_signing_key' => 'test-secret']);
});

it('generates a valid token', function () {
    $service = new EventloketTokenService;

    $token = $service->generate(Str::uuid(), Str::uuid());

    expect($token)->toBeString()->not->toBeEmpty();
});

it('validates a freshly generated token', function () {
    $userUuid = (string) Str::uuid();
    $orgUuid = (string) Str::uuid();

    $user = User::factory()->create(['uuid' => $userUuid, 'role' => Role::Organiser]);
    $org = Organisation::factory()->create(['uuid' => $orgUuid]);
    $user->organisations()->attach($org, ['role' => 'owner']);

    $service = new EventloketTokenService;
    $token = $service->generate($userUuid, $orgUuid);

    $result = $service->validate($token);

    expect($result)->not->toBeNull()
        ->and($result['user']->uuid)->toBe($userUuid)
        ->and($result['organisation']->uuid)->toBe($orgUuid);
});

it('rejects a tampered token', function () {
    $service = new EventloketTokenService;
    $token = $service->generate(Str::uuid(), Str::uuid());

    $result = $service->validate($token.'tampered');

    expect($result)->toBeNull();
});

it('rejects an expired token', function () {
    $service = new EventloketTokenService;
    $token = $service->generate(Str::uuid(), Str::uuid());

    $this->travel(6)->minutes();

    $result = $service->validate($token);

    expect($result)->toBeNull();
});

it('allows multiple validations of the same token', function () {
    $userUuid = (string) Str::uuid();
    $orgUuid = (string) Str::uuid();

    $user = User::factory()->create(['uuid' => $userUuid, 'role' => Role::Organiser]);
    $org = Organisation::factory()->create(['uuid' => $orgUuid]);
    $user->organisations()->attach($org, ['role' => 'owner']);

    $service = new EventloketTokenService;
    $token = $service->generate($userUuid, $orgUuid);

    $result1 = $service->validate($token);
    $result2 = $service->validate($token);

    expect($result1)->not->toBeNull()
        ->and($result2)->not->toBeNull();
});

it('rejects a token with unknown user', function () {
    $service = new EventloketTokenService;
    $token = $service->generate(Str::uuid(), Str::uuid());

    $result = $service->validate($token);

    expect($result)->toBeNull();
});
