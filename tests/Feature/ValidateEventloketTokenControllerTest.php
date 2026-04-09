<?php

use App\Enums\Role;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EventloketTokenService;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

beforeEach(function () {
    config(['services.open_forms.token_signing_key' => 'test-secret']);

    $client = Client::factory()->asClientCredentials()->create(['secret' => '12345678']);

    $response = $this->postJson(route('passport.token'), [
        'grant_type' => 'client_credentials',
        'client_id' => $client->id,
        'client_secret' => '12345678',
    ]);

    $this->access_token = $response->json('access_token');
});

it('returns contact data for a valid token', function () {
    $userUuid = (string) Str::uuid();
    $orgUuid = (string) Str::uuid();

    User::factory()->create([
        'uuid' => $userUuid,
        'role' => Role::Organiser,
        'first_name' => 'Jan',
        'last_name' => 'Tester',
        'email' => 'jan@test.nl',
    ]);
    Organisation::factory()->create([
        'uuid' => $orgUuid,
        'name' => 'Test Org',
        'coc_number' => '12345678',
    ]);

    $token = app(EventloketTokenService::class)->generate($userUuid, $orgUuid);

    $response = $this->getJson(
        "/api/validate-eventloket-token?token={$token}",
        ['Authorization' => "Bearer {$this->access_token}"],
    );

    $response->assertOk()
        ->assertJson([
            'valid' => true,
            'identifier' => $userUuid,
            'data' => [
                'user_email' => 'jan@test.nl',
                'user_first_name' => 'Jan',
                'user_last_name' => 'Tester',
                'organisation_name' => 'Test Org',
                'kvk' => '12345678',
            ],
        ]);
});

it('returns invalid for a bad token', function () {
    $response = $this->getJson(
        '/api/validate-eventloket-token?token=invalid-token',
        ['Authorization' => "Bearer {$this->access_token}"],
    );

    $response->assertOk()
        ->assertJson(['data' => ['valid' => false]]);
});

it('returns validation error when token is missing', function () {
    $response = $this->getJson(
        '/api/validate-eventloket-token',
        ['Authorization' => "Bearer {$this->access_token}"],
    );

    $response->assertStatus(422);
});

it('requires authentication', function () {
    $response = $this->getJson('/api/validate-eventloket-token?token=some-token');

    $response->assertStatus(401);
});
