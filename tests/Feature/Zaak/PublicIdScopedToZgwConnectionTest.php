<?php

/**
 * `zaken.public_id` holds the identificatie the ZGW instance assigned to the
 * zaak. Each instance runs its own numbering, so the same number can be handed
 * out by two of them. While the unique index was global the second of those two
 * zaken could not be stored: the ZGW create had already succeeded, the local
 * insert failed on the index, and the surrounding transaction rolled the local
 * side back.
 *
 * These tests pin both halves of the narrowed guarantee: a number may repeat
 * across connections, and may still not repeat within one.
 */

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

/**
 * A municipality that runs its own ZGW instance, with a zaaktype hosted there.
 */
function zaaktypeOnOwnInstance(): Zaaktype
{
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->active()->create();

    return Zaaktype::factory()->for($municipality)->create([
        'connection' => "gemeente_{$municipality->id}",
    ]);
}

it('stores the connection that issued the zaaknummer', function () {
    $ownInstance = zaaktypeOnOwnInstance();
    $shared = Zaaktype::factory()->for(Municipality::factory())->create(['connection' => 'main']);

    $own = Zaak::factory()->for($ownInstance)->create();
    $main = Zaak::factory()->for($shared)->create();

    expect($own->fresh()->zgw_connection)->toBe("gemeente_{$ownInstance->municipality_id}")
        ->and($main->fresh()->zgw_connection)->toBe('main');
});

it('accepts one zaaknummer on two different ZGW connections', function () {
    $first = zaaktypeOnOwnInstance();
    $second = zaaktypeOnOwnInstance();

    $number = 'ZAAK-2026-000000042';

    $one = Zaak::factory()->for($first)->create(['public_id' => $number]);
    $two = Zaak::factory()->for($second)->create(['public_id' => $number]);

    expect(Zaak::where('public_id', $number)->count())->toBe(2)
        ->and($one->fresh()->zgw_connection)->not->toBe($two->fresh()->zgw_connection);
});

it('accepts one zaaknummer on a municipality instance and on the shared connection', function () {
    $ownInstance = zaaktypeOnOwnInstance();
    $shared = Zaaktype::factory()->for(Municipality::factory())->create(['connection' => 'main']);

    $number = 'ZAAK-2026-000000043';

    Zaak::factory()->for($ownInstance)->create(['public_id' => $number]);
    Zaak::factory()->for($shared)->create(['public_id' => $number]);

    expect(Zaak::where('public_id', $number)->pluck('zgw_connection')->sort()->values()->all())
        ->toBe(['gemeente_'.$ownInstance->municipality_id, 'main']);
});

it('still rejects one zaaknummer twice on the same connection', function () {
    $zaaktype = zaaktypeOnOwnInstance();

    $number = 'ZAAK-2026-000000044';

    Zaak::factory()->for($zaaktype)->create(['public_id' => $number]);

    expect(fn () => Zaak::factory()->for($zaaktype)->create(['public_id' => $number]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('still rejects one zaaknummer twice on the shared connection', function () {
    $zaaktype = Zaaktype::factory()->for(Municipality::factory())->create(['connection' => 'main']);

    $number = 'ZAAK-2026-000000045';

    Zaak::factory()->for($zaaktype)->create(['public_id' => $number]);

    expect(fn () => Zaak::factory()->for($zaaktype)->create(['public_id' => $number]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('keeps a zaak on the shared connection when the municipality connection is not live yet', function () {
    // Not activated: the resolver routes submissions to the shared connection
    // until the municipality goes live, so that is where the number came from.
    $municipality = Municipality::factory()->create();
    MunicipalityZgwConnection::factory()->for($municipality)->create();

    $zaaktype = Zaaktype::factory()->for($municipality)->create([
        'connection' => "gemeente_{$municipality->id}",
    ]);

    expect(Zaak::factory()->for($zaaktype)->create()->fresh()->zgw_connection)->toBe('main');
});

it('records an explicitly supplied connection unchanged', function () {
    // The import path saves without model events and fills the column itself.
    $zaaktype = zaaktypeOnOwnInstance();

    $zaak = Zaak::factory()->for($zaaktype)->create(['zgw_connection' => 'main']);

    expect($zaak->fresh()->zgw_connection)->toBe('main');
});
