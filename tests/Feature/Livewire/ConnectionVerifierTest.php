<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\ConnectionVerifier;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use App\Models\ZgwAbonnement;
use App\Services\Notificaties\AbonnementRegistrar;
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

/**
 * Fakes the per-component read checks the verifier runs after the base
 * connection check. Pass an api name to make that one fail.
 */
function fakeApiReads(?string $failing = null): array
{
    $endpoints = [
        'zaken' => GEM.'/zaken/api/v1/zaken*',
        'documenten' => GEM.'/documenten/api/v1/enkelvoudiginformatieobjecten*',
        'besluiten' => GEM.'/besluiten/api/v1/besluiten*',
    ];

    $fakes = [];
    foreach ($endpoints as $api => $pattern) {
        $fakes[$pattern] = $api === $failing
            ? Http::response(['detail' => 'not found'], 404)
            : Http::response(['count' => 0, 'next' => null, 'previous' => null, 'results' => []], 200);
    }

    return $fakes;
}

function healthyAbonnement(int $municipalityId, string $name): string
{
    $aboUrl = GEM.'/notificaties/api/v1/abonnement/abc';

    ZgwAbonnement::factory()->create([
        'connection' => $name,
        'municipality_id' => $municipalityId,
        'notificaties_base_url' => GEM.'/notificaties/api/v1/',
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

it('refuses a connection that mixes its own instance with the main one', function () {
    // Its catalogi API is its own, its zaken API is left empty and therefore
    // inherited from the main connection. Zaaktypen are then read on one
    // instance while zaken are created on the other, which the receiving
    // instance rejects. The per-API reads cannot see this: a query on the
    // inherited instance answers 200 with no results.
    $this->connection->updateQuietly(['zaken_url' => null]);

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.urls.status', 'fail')
        ->assertSee(__('municipality/resources/zgw_connection.actions.verify.urls.names.zaken'))
        ->assertSet('steps.connection.status', 'skipped')
        ->assertSet('success', false);

    expect($this->connection->refresh()->last_verified_at)->toBeNull();
    Http::assertNothingSent();
});

it('accepts a connection that inherits every url from the main connection', function () {
    // A municipality on the same instance as the main connection, overriding
    // only its credentials: nothing is mixed, so the coherence check passes.
    $this->connection->updateQuietly([
        'zaken_url' => null,
        'catalogi_url' => null,
        'documenten_url' => null,
        'besluiten_url' => null,
        'notificaties_url' => null,
    ]);

    Http::fake([
        rtrim((string) config('zgw.connections.main.urls.catalogi'), '/').'/catalogussen*' => Http::response(
            ['count' => 0, 'next' => null, 'previous' => null, 'results' => []],
            200,
        ),
        '*' => Http::response(['count' => 0, 'next' => null, 'previous' => null, 'results' => []], 200),
    ]);

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.urls.status', 'success');
});

it('completes and stamps the connection when the abonnement is healthy', function () {
    $aboUrl = healthyAbonnement($this->connection->municipality_id, $this->name);
    Http::fake(array_merge(fakeApiReads(), [
        $aboUrl => Http::response([
            'url' => $aboUrl,
            'kanalen' => collect(AbonnementRegistrar::KANALEN)->map(fn (string $n): array => ['naam' => $n])->all(),
        ], 200),
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
    ]));

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.connection.status', 'success')
        ->assertSet('steps.apis.status', 'success')
        ->assertSet('steps.abonnement.status', 'success')
        ->assertSet('finished', true)
        ->assertSet('success', true);

    expect($this->connection->refresh()->last_verified_at)->not->toBeNull();
});

it('reports which API is unreadable and stops before the abonnement step', function (string $failing, string $label) {
    // The base check only reads the catalogi API, so a wrong or omitted base URL
    // for another component used to stay invisible until a zaak page came up
    // empty. An omitted URL inherits the main connection's, where a query about
    // this instance's zaak answers 200 with no results.
    Http::fake(array_merge(fakeApiReads(failing: $failing), [
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
    ]));

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.connection.status', 'success')
        ->assertSet('steps.apis.status', 'fail')
        ->assertSee($label)
        ->assertSet('steps.abonnement.status', 'skipped')
        ->assertSet('success', false);

    expect($this->connection->refresh()->last_verified_at)->toBeNull();
})->with([
    'zaken' => ['zaken', 'Zaken'],
    'documenten' => ['documenten', 'Documenten'],
    'besluiten' => ['besluiten', 'Besluiten'],
]);

it('offers a register button and registers the abonnement', function () {
    $base = GEM.'/notificaties/api/v1/';
    $aboUrl = $base.'abonnement/abc';

    Http::fake(array_merge(fakeApiReads(), [
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
    ]));

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('needsRegister', true)
        ->assertSet('steps.abonnement.status', 'action')
        ->call('register')
        ->assertSet('steps.abonnement.status', 'success')
        ->assertSet('needsRegister', false);

    expect(ZgwAbonnement::where('connection', $this->name)->exists())->toBeTrue();
});

it('does not offer to register an abonnement on the notificaties api it inherits from main', function () {
    // Registering here would put this connection's token on main's abonnement,
    // after which main's notifications are attributed to this connection.
    config()->set('zgw.connections.main.urls.notificaties', 'https://nc-main.example.com/api/v1/');
    $this->connection->updateQuietly(['notificaties_url' => null]);

    Http::fake(array_merge(fakeApiReads(), [
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
    ]));

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.abonnement.status', 'warning')
        ->assertSet('needsRegister', false)
        ->assertSee(__('municipality/resources/zgw_connection.actions.verify.abonnement.notificaties_url_inherited'))
        ->assertSet('success', true);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'nc-main.example.com'));
    expect(ZgwAbonnement::where('connection', $this->name)->exists())->toBeFalse();
});

it('fails the abonnement step when another connection stores the same abonnement', function () {
    $aboUrl = healthyAbonnement($this->connection->municipality_id, $this->name);
    ZgwAbonnement::factory()->create(['connection' => 'main', 'abonnement_url' => $aboUrl]);

    Http::fake(array_merge(fakeApiReads(), [
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
    ]));

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('steps.abonnement.status', 'fail')
        ->assertSet('needsRegister', false)
        ->assertSee(__('municipality/resources/zgw_connection.actions.verify.abonnement.shared_abonnement', [
            'owner' => __('municipality/resources/zgw_connection.actions.verify.abonnement.owner.main'),
        ]))
        ->assertSet('success', false);

    expect($this->connection->refresh()->last_verified_at)->toBeNull();
});

it('explains a refused registration when another connection subscribed on the same instance under another url', function () {
    $base = GEM.'/notificaties/api/v1/';
    $otherAbonnement = $base.'abonnement/other';

    // Another connection reaches this instance through an alias and has
    // already subscribed there, so the instance-url check cannot see it.
    ZgwAbonnement::factory()->create([
        'connection' => 'gemeente_999999',
        'notificaties_base_url' => 'https://alias.example.com/notificaties/api/v1/',
        'abonnement_url' => $otherAbonnement,
    ]);

    Http::fake(array_merge(fakeApiReads(), [
        GEM.'/catalogi/api/v1/catalogussen*' => Http::response(
            ['count' => 1, 'next' => null, 'previous' => null, 'results' => [['url' => GEM.'/catalogi/api/v1/catalogussen/1']]],
            200,
        ),
        $base.'abonnement' => Http::response([
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [['url' => $otherAbonnement, 'callbackUrl' => app(AbonnementRegistrar::class)->callbackUrl()]],
        ], 200),
    ]));

    livewire(ConnectionVerifier::class, ['connection' => $this->connection])
        ->call('start')
        ->assertSet('needsRegister', true)
        ->call('register')
        ->assertSet('steps.abonnement.status', 'warning')
        ->assertSet('needsRegister', false)
        ->assertSee(__('municipality/resources/zgw_connection.actions.verify.abonnement.notificaties_instance_in_use', [
            'owner' => __('municipality/resources/zgw_connection.actions.verify.abonnement.owner.other'),
        ]))
        ->assertSet('success', true);

    Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['POST', 'PATCH'], true)
        && str_starts_with($request->url(), $base));
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
