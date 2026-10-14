<?php

/**
 * The backfill and the rollback guard of the migration that scopes
 * `zaken.public_id` to a ZGW connection.
 *
 * Both are exercised directly rather than through `up()` and `down()`. The
 * schema change around them is dialect-specific DDL, and running DDL inside the
 * transaction a test lives in behaves differently per database engine; the
 * attribution rule and the refusal are the parts worth pinning, and they are
 * plain queries.
 */

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Load the migration under test. It is an anonymous class, so it has to be
 * required rather than instantiated by name.
 */
function publicIdScopeMigration(): Migration
{
    return require database_path('migrations/2026_09_10_143000_scope_zaken_public_id_to_zgw_connection.php');
}

/**
 * Put every row back in the state the migration finds it in: the column exists
 * and holds its default, nothing has been attributed yet.
 */
function resetConnectionColumnToDefault(): void
{
    DB::table('zaken')->update(['zgw_connection' => 'main']);
}

it('attributes existing zaken across several connections, defaulting to the shared one', function () {
    // Two municipalities on their own instance, one on the shared connection,
    // and one whose own connection exists but has not gone live.
    $first = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($first)->active()->create();

    $second = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($second)->active()->create();

    $shared = Municipality::factory()->create();

    $pending = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($pending)->create();

    $firstZaaktype = Zaaktype::factory()->for($first)->create(['connection' => "gemeente_{$first->id}"]);
    $secondZaaktype = Zaaktype::factory()->for($second)->create(['connection' => "gemeente_{$second->id}"]);
    // A main-catalogus zaaktype linked to an own-instance municipality: the zaak
    // is created on the shared connection, so that is where its number is from.
    $fallbackZaaktype = Zaaktype::factory()->for($first)->create(['connection' => 'main']);
    $sharedZaaktype = Zaaktype::factory()->for($shared)->create(['connection' => 'main']);
    $pendingZaaktype = Zaaktype::factory()->for($pending)->create(['connection' => "gemeente_{$pending->id}"]);

    $zaken = [
        'first' => Zaak::factory()->for($firstZaaktype)->create(),
        'second' => Zaak::factory()->for($secondZaaktype)->create(),
        'fallback' => Zaak::factory()->for($fallbackZaaktype)->create(),
        'shared' => Zaak::factory()->for($sharedZaaktype)->create(),
        'pending' => Zaak::factory()->for($pendingZaaktype)->create(),
        // A row that no longer points at a zaaktype cannot be attributed and
        // has to keep the default. Created without model events because the
        // unrelated 'created' handler dereferences the zaaktype.
        'zaaktypeless' => Zaak::withoutEvents(fn (): Zaak => Zaak::factory()->create(['zaaktype_id' => null])),
    ];

    resetConnectionColumnToDefault();

    publicIdScopeMigration()->backfillConnections();

    $resolved = collect($zaken)->map(
        fn (Zaak $zaak): string => (string) DB::table('zaken')->where('id', $zaak->id)->value('zgw_connection')
    );

    expect($resolved->all())->toBe([
        'first' => "gemeente_{$first->id}",
        'second' => "gemeente_{$second->id}",
        'fallback' => 'main',
        'shared' => 'main',
        // Not activated, so submissions still ran on the shared connection.
        'pending' => 'main',
        'zaaktypeless' => 'main',
    ]);
});

it('runs the backfill twice without changing the outcome', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->active()->create();
    $zaaktype = Zaaktype::factory()->for($municipality)->create([
        'connection' => "gemeente_{$municipality->id}",
    ]);
    $zaak = Zaak::factory()->for($zaaktype)->create();

    resetConnectionColumnToDefault();

    publicIdScopeMigration()->backfillConnections();
    publicIdScopeMigration()->backfillConnections();

    expect(DB::table('zaken')->where('id', $zaak->id)->value('zgw_connection'))
        ->toBe("gemeente_{$municipality->id}");
});

it('allows a rollback while every zaaknummer is still held by one row', function () {
    $municipality = Municipality::factory()->create();
    $zaaktype = Zaaktype::factory()->for($municipality)->create(['connection' => 'main']);

    Zaak::factory()->for($zaaktype)->create(['public_id' => 'ZAAK-2026-000000201']);
    Zaak::factory()->for($zaaktype)->create(['public_id' => 'ZAAK-2026-000000202']);

    publicIdScopeMigration()->assertPublicIdIsGloballyUnique();
})->throwsNoExceptions();

it('refuses a rollback once a zaaknummer is in use on two connections', function () {
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->active()->create();

    $own = Zaaktype::factory()->for($municipality)->create([
        'connection' => "gemeente_{$municipality->id}",
    ]);
    $shared = Zaaktype::factory()->for(Municipality::factory())->create(['connection' => 'main']);

    $number = 'ZAAK-2026-000000203';

    Zaak::factory()->for($own)->create(['public_id' => $number]);
    Zaak::factory()->for($shared)->create(['public_id' => $number]);

    expect(fn () => publicIdScopeMigration()->assertPublicIdIsGloballyUnique())
        ->toThrow(RuntimeException::class, $number);
});

it('ignores rows without a zaaknummer when checking whether a rollback is possible', function () {
    // Imported cases carry no ZGW number at all; several of them are not a
    // collision and must not block a rollback.
    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create(['connection' => 'main']);

    Zaak::factory()->for($zaaktype)->create(['public_id' => null]);
    Zaak::factory()->for($zaaktype)->create(['public_id' => null]);

    publicIdScopeMigration()->assertPublicIdIsGloballyUnique();
})->throwsNoExceptions();
