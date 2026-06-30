<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\ConnectionVerifier;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\NotificationRoundTripProbe;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

const GEM = 'https://gemeente.example.com';

/**
 * A managing user (KoppelingBeheerder by default) attached to the given municipality.
 */
function managingUser(int $municipalityId, Role $role = Role::KoppelingBeheerder): User
{
    $user = User::factory()->create(['role' => $role]);
    Municipality::findOrFail($municipalityId)->users()->attach($user);

    return User::findOrFail($user->id);
}

beforeEach(function () {
    Cache::flush();
    $this->connection = MunicipalityZgwConnection::factory()->create();
    $this->name = "gemeente_{$this->connection->municipality_id}";

    // The verifier authorises on MunicipalityZgwConnectionPolicy::verify, so the
    // happy-path tests act as a managing user of the connection's municipality.
    $this->actingAs(managingUser($this->connection->municipality_id));
});

function fakeConnectionOk(): void
{
    Http::fake([
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
    ]);
}

function healthyAbonnement(int $municipalityId, string $name): string
{
    $aboUrl = GEM.'/notificaties/api/v1/abonnement/abc';

    ZgwAbonnement::factory()->create([
        'connection' => $name,
        'municipality_id' => $municipalityId,
        'abonnement_url' => $aboUrl,
        'expires_at' => now()->addYear(),
    ]);

    return $aboUrl;
}

it('fails fast when the connection is unreachable', function () {
    Http::fake([GEM.'/catalogi/api/v1/catalogussen*' => Http::response(['detail' => 'unauthorized'], 401)]);

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.connection.status', 'fail')
        // The raw exception message must never reach the user; only the generic one.
        ->assertSet('steps.connection.message', __('municipality/resources/zgw_connection.actions.verify.connection.error'))
        ->assertSet('finished', true)
        ->assertSet('success', false);
});

it('completes and stamps the connection on local where the round trip is skipped', function () {
    $this->app['env'] = 'local';

    fakeConnectionOk();
    $aboUrl = healthyAbonnement($this->connection->municipality_id, $this->name);
    Http::fake([
        $aboUrl => Http::response([
            'url' => $aboUrl,
            'kanalen' => collect(AbonnementRegistrar::KANALEN)->map(fn (string $n): array => ['naam' => $n])->all(),
        ], 200),
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
    ]);

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.connection.status', 'success')
        ->assertSet('steps.abonnement.status', 'success')
        ->assertSet('steps.notification.status', 'skipped')
        ->assertSet('finished', true)
        ->assertSet('success', true);

    expect($this->connection->refresh()->last_verified_at)->not->toBeNull();
});

it('runs the round trip and stamps the connection outside local', function () {
    // testing env is not local, so the notification step is offered.
    $aboUrl = healthyAbonnement($this->connection->municipality_id, $this->name);

    Http::fake([
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
        $aboUrl => Http::response([
            'url' => $aboUrl,
            'kanalen' => collect(AbonnementRegistrar::KANALEN)->map(fn (string $n): array => ['naam' => $n])->all(),
        ], 200),
        GEM.'/notificaties/api/v1/notificaties' => function (Request $request) {
            NotificationRoundTripProbe::recordReceipt(data_get($request->data(), 'kenmerken.probe_id'));

            return Http::response([], 201);
        },
    ]);

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.abonnement.status', 'success')
        ->assertSet('awaitingSend', true)
        ->call('sendTest')
        ->call('poll')
        ->assertSet('steps.notification.status', 'success')
        ->assertSet('finished', true)
        ->assertSet('success', true);

    expect($this->connection->refresh()->last_verified_at)->not->toBeNull();
});

it('offers a register button and registers the abonnement', function () {
    $base = GEM.'/notificaties/api/v1/';
    $aboUrl = $base.'abonnement/abc';

    Http::fake([
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
        rtrim((string) config('app.url'), '/').'/oauth/token' => Http::response([
            'access_token' => 'header.payload.signature',
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
        ], 200),
        $base.'abonnement' => function (Request $request) use ($base) {
            return $request->method() === 'GET'
                ? Http::response(['count' => 0, 'next' => null, 'previous' => null, 'results' => []], 200)
                : Http::response(['url' => $base.'abonnement/abc'], 201);
        },
        $aboUrl => Http::response([
            'url' => $aboUrl,
            'kanalen' => collect(AbonnementRegistrar::KANALEN)->map(fn (string $n): array => ['naam' => $n])->all(),
        ], 200),
    ]);

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('needsRegister', true)
        ->assertSet('steps.abonnement.status', 'action')
        ->call('register')
        ->assertSet('steps.abonnement.status', 'success')
        ->assertSet('needsRegister', false);

    expect(ZgwAbonnement::where('connection', $this->name)->exists())->toBeTrue();
});

it('offers a retry when the round trip times out', function () {
    $aboUrl = healthyAbonnement($this->connection->municipality_id, $this->name);

    Http::fake([
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
        $aboUrl => Http::response([
            'url' => $aboUrl,
            'kanalen' => collect(AbonnementRegistrar::KANALEN)->map(fn (string $n): array => ['naam' => $n])->all(),
        ], 200),
        // Publish succeeds but the notification never returns.
        GEM.'/notificaties/api/v1/notificaties' => Http::response([], 201),
    ]);

    $component = livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->call('sendTest')
        ->assertSet('waiting', true);

    $this->travel(16)->seconds();

    $component->call('poll')
        ->assertSet('steps.notification.status', 'fail')
        ->assertSet('canRetry', true)
        ->assertSet('finished', false);

    expect($this->connection->refresh()->last_verified_at)->toBeNull();
});

it('forbids verifying a connection of another municipality', function () {
    // A managing user, but of a different municipality than the connection.
    $this->actingAs(managingUser(Municipality::factory()->create()->id));

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->assertForbidden();
});

it('forbids a non-managing role from verifying the connection', function () {
    // Attached to the right municipality, but a role that may not manage connections.
    $this->actingAs(managingUser($this->connection->municipality_id, Role::Reviewer));

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->assertForbidden();
});
